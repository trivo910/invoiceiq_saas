<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BillingService
{
    public function subscription(Tenant $tenant): ?TenantSubscription
    {
        return $tenant->subscriptions()->whereNotIn('status', ['pending', 'checkout_failed'])->with('plan')->latest('id')->first()
            ?? $tenant->subscriptions()->where('status', 'pending')->with('plan')->latest('id')->first();
    }

    public function canWrite(Tenant $tenant): bool
    {
        if ($tenant->status !== 'active') return false;
        $subscription = $this->subscription($tenant);
        if (! $subscription) return false;

        return match ($subscription->status) {
            'active' => $subscription->plan?->code === 'legacy'
                || ($subscription->current_period_ends_at?->isFuture() ?? false),
            'trialing' => $subscription->trial_ends_at?->isFuture() ?? false,
            // If no grace policy has been configured, keep access until the product decision is supplied.
            'past_due' => $this->graceEndsAt($subscription)?->isFuture() ?? true,
            'cancelled' => $subscription->current_period_ends_at?->isFuture() ?? false,
            'completed' => $subscription->current_period_ends_at?->isFuture() ?? false,
            'paused' => $subscription->current_period_ends_at?->isFuture() ?? false,
            default => false,
        };
    }

    public function graceEndsAt(TenantSubscription $subscription): ?\Illuminate\Support\Carbon
    {
        if ($subscription->grace_ends_at) return $subscription->grace_ends_at;
        $graceDays = config('billing.grace_days');
        if ($graceDays === null) return null;
        $graceDays = filter_var($graceDays, FILTER_VALIDATE_INT);
        if ($graceDays === false || $graceDays < 0) {
            throw new InvalidArgumentException('BILLING_GRACE_DAYS must be a non-negative integer.');
        }

        $graceStartedAt = $subscription->past_due_started_at ?? $subscription->provider_state_at ?? $subscription->updated_at;
        return $graceStartedAt?->copy()->addDays($graceDays);
    }

    public function webhookConfigured(): bool
    {
        $secret = config('billing.razorpay.webhook_secret');
        return is_string($secret) && trim($secret) !== '';
    }

    public function taxDetailsConfigured(): bool
    {
        $seller = config('billing.seller');
        if (! is_array($seller)) return false;

        foreach (['name', 'gstin', 'pan', 'address', 'state_code', 'sac_code'] as $field) {
            if (! is_string($seller[$field] ?? null) || trim($seller[$field]) === '') return false;
        }

        return in_array($seller['price_tax_mode'] ?? null, ['inclusive', 'exclusive'], true)
            && is_numeric($seller['gst_rate'] ?? null)
            && (float) $seller['gst_rate'] > 0
            && (float) $seller['gst_rate'] <= 100;
    }

    public function canStartCheckout(Tenant $tenant): bool
    {
        $subscription = $this->subscription($tenant);
        if (! $subscription || $subscription->plan?->code === 'legacy' || $subscription->status === 'trialing') return true;
        if ($subscription->status === 'active') return ! ($subscription->current_period_ends_at?->isFuture() ?? false);
        if ($subscription->status === 'past_due') return ! $this->canWrite($tenant);
        return in_array($subscription->status, ['expired', 'cancelled', 'completed', 'paused'], true)
            && ! $subscription->current_period_ends_at?->isFuture();
    }

    public function usage(Tenant $tenant): array
    {
        return [
            'invoices' => $tenant->invoices()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'members' => $tenant->users()->count() + \App\Models\TenantInvitation::where('tenant_id', $tenant->id)->whereNull('accepted_at')->where('expires_at', '>', now())->count(),
        ];
    }

    public function limitReached(Tenant $tenant, string $resource, ?int $exceptInvitationId = null): bool
    {
        $subscription = $this->subscription($tenant);
        if (! $subscription || ! $subscription->plan) return true;
        $usage = $this->usage($tenant);
        if ($resource === 'members' && $exceptInvitationId) {
            $usage['members'] -= \App\Models\TenantInvitation::whereKey($exceptInvitationId)->where('tenant_id', $tenant->id)->whereNull('accepted_at')->where('expires_at', '>', now())->exists() ? 1 : 0;
        }
        $limit = match ($resource) {
            'invoices' => $subscription->plan->monthly_invoice_limit,
            'members' => $subscription->plan->member_limit,
            default => null,
        };
        return $limit !== null && ($usage[$resource] ?? 0) >= $limit;
    }

    public function checkoutReady(Plan $plan, string $interval): bool
    {
        if (config('billing.demo_mode')) return false;
        if (! in_array($interval, ['monthly', 'yearly'], true)) return false;
        if (in_array($plan->code, ['legacy', 'trial'], true)) return false;
        $cycleCount = config("billing.subscription_cycle_count.{$interval}");
        $maximumCycles = $interval === 'monthly' ? 1200 : 100;
        $featuresConfigured = $plan->gst_reports !== null && $plan->custom_branding !== null && $plan->api_access !== null;
        $price = $interval === 'monthly' ? $plan->monthly_price_paise : $plan->yearly_price_paise;
        $providerPlanId = $interval === 'monthly' ? $plan->razorpay_plan_monthly_id : $plan->razorpay_plan_yearly_id;
        return $plan->is_active
            && $featuresConfigured
            && $this->taxDetailsConfigured()
            && $price !== null && $price > 0
            && $providerPlanId
            && config('billing.razorpay.key') && config('billing.razorpay.secret')
            && $this->webhookConfigured()
            && is_numeric($cycleCount) && (int) $cycleCount > 0 && (int) $cycleCount <= $maximumCycles;
    }

    public function createCheckout(Tenant $tenant, Plan $plan, string $interval): TenantSubscription
    {
        abort_unless($this->checkoutReady($plan, $interval), 422, 'This plan is not configured for checkout.');
        return DB::transaction(function () use ($tenant, $plan, $interval): TenantSubscription {
            abort_unless(DB::table('tenants')->where('id', $tenant->id)->lockForUpdate()->first(), 404);
            $existingPending = $tenant->subscriptions()->where('status', 'pending')->latest('id')->lockForUpdate()->first();
            if ($existingPending) {
                $pendingUrl = $existingPending->provider_metadata['short_url'] ?? null;
                if (! $this->validCheckoutUrl($pendingUrl)) {
                    $providerId = $existingPending->razorpay_subscription_id;
                    abort_unless(is_string($providerId) && $providerId !== '', 409, 'The existing checkout link is unavailable. Contact support to restore access.');
                    $api = Http::timeout(10)->withBasicAuth(config('billing.razorpay.key'), config('billing.razorpay.secret'))->acceptJson();
                    $providerSubscription = $api->get(rtrim(config('billing.razorpay.api_url'), '/').'/subscriptions/'.rawurlencode($providerId))->throw()->json();
                    abort_unless(is_array($providerSubscription), 409, 'The existing checkout link is unavailable. Contact support to restore access.');
                    $pendingUrl = $providerSubscription['short_url'] ?? null;
                    abort_unless(
                        ($providerSubscription['id'] ?? null) === $providerId && $this->validCheckoutUrl($pendingUrl),
                        409,
                        'The existing checkout link is unavailable. Contact support to restore access.'
                    );
                    $metadata = $existingPending->provider_metadata ?? [];
                    $metadata['short_url'] = $pendingUrl;
                    $existingPending->update(['provider_metadata' => $metadata]);
                }
                return $existingPending;
            }

            $planId = $interval === 'monthly' ? $plan->razorpay_plan_monthly_id : $plan->razorpay_plan_yearly_id;
            $api = Http::timeout(10)->withBasicAuth(config('billing.razorpay.key'), config('billing.razorpay.secret'))->acceptJson();
            $providerPlan = $api->get(rtrim(config('billing.razorpay.api_url'), '/').'/plans/'.$planId)->throw()->json();
            $configuredPrice = $interval === 'monthly' ? $plan->monthly_price_paise : $plan->yearly_price_paise;
            $providerPrice = (int) $configuredPrice;
            if (config('billing.seller.price_tax_mode') === 'exclusive') {
                $providerPrice += (int) round($providerPrice * (float) config('billing.seller.gst_rate') / 100);
            }
            abort_unless(
                (int) data_get($providerPlan, 'item.amount') === $providerPrice
                && strtoupper((string) data_get($providerPlan, 'item.currency')) === 'INR'
                && (string) data_get($providerPlan, 'period') === $interval,
                422,
                'The configured Razorpay plan does not match this plan price and billing interval.'
            );

            $response = $api
                ->acceptJson()->asJson()->post(rtrim(config('billing.razorpay.api_url'), '/').'/subscriptions', [
                    'plan_id' => $planId,
                    'total_count' => (int) config("billing.subscription_cycle_count.{$interval}"),
                    'quantity' => 1,
                    'customer_notify' => 1,
                    'notes' => ['tenant_id' => (string) $tenant->id, 'local_reference' => (string) Str::uuid()],
                ])->throw()->json();
            $providerSubscriptionId = $response['id'] ?? null;
            $checkoutUrl = $response['short_url'] ?? null;
            abort_unless(
                is_string($providerSubscriptionId) && $providerSubscriptionId !== ''
                && $this->validCheckoutUrl($checkoutUrl),
                502,
                'The payment provider did not return a valid checkout link.'
            );

            return $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => 'pending',
                'billing_interval' => $interval,
                'razorpay_subscription_id' => $providerSubscriptionId,
                'provider_metadata' => ['short_url' => $checkoutUrl],
            ]);
        });
    }

    private function validCheckoutUrl(mixed $url): bool
    {
        return is_string($url) && str_starts_with($url, 'https://rzp.io/');
    }
}
