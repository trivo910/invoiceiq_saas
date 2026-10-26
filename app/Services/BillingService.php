<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BillingService
{
    public function subscription(Tenant $tenant): ?TenantSubscription
    {
        return $tenant->subscriptions()->where('status', '!=', 'pending')->with('plan')->latest('id')->first()
            ?? $tenant->subscriptions()->with('plan')->latest('id')->first();
    }

    public function canWrite(Tenant $tenant): bool
    {
        $subscription = $this->subscription($tenant);
        if (! $subscription) return false;

        return match ($subscription->status) {
            'active' => $subscription->current_period_ends_at?->isFuture() ?? true,
            'trialing' => $subscription->trial_ends_at?->isFuture() ?? false,
            // If no grace policy has been configured, keep access until the product decision is supplied.
            'past_due' => $subscription->grace_ends_at?->isFuture() ?? true,
            'cancelled' => $subscription->current_period_ends_at?->isFuture() ?? false,
            default => false,
        };
    }

    public function canStartCheckout(Tenant $tenant): bool
    {
        $subscription = $this->subscription($tenant);
        if (! $subscription || $subscription->plan?->code === 'legacy' || $subscription->status === 'trialing') return true;
        return in_array($subscription->status, ['expired', 'cancelled', 'completed'], true)
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
        $cycleCount = config("billing.subscription_cycle_count.{$interval}");
        $maximumCycles = $interval === 'monthly' ? 1200 : 100;
        $featuresConfigured = $plan->gst_reports !== null && $plan->custom_branding !== null && $plan->api_access !== null;
        $seller = config('billing.seller');
        $taxConfigured = in_array($seller['price_tax_mode'], ['inclusive', 'exclusive'], true)
            && is_numeric($seller['gst_rate']) && (float) $seller['gst_rate'] > 0 && (float) $seller['gst_rate'] <= 100
            && $seller['name'] && $seller['gstin'] && $seller['pan'] && $seller['address'] && $seller['state_code'] && $seller['sac_code'];
        return $plan->is_active
            && $featuresConfigured
            && $taxConfigured
            && ($interval === 'monthly' ? $plan->monthly_price_paise : $plan->yearly_price_paise) !== null
            && ($interval === 'monthly' ? $plan->razorpay_plan_monthly_id : $plan->razorpay_plan_yearly_id)
            && config('billing.razorpay.key') && config('billing.razorpay.secret')
            && is_numeric($cycleCount) && (int) $cycleCount > 0 && (int) $cycleCount <= $maximumCycles;
    }

    public function createCheckout(Tenant $tenant, Plan $plan, string $interval): TenantSubscription
    {
        abort_unless($this->checkoutReady($plan, $interval), 422, 'This plan is not configured for checkout.');
        $planId = $interval === 'monthly' ? $plan->razorpay_plan_monthly_id : $plan->razorpay_plan_yearly_id;
        $api = Http::withBasicAuth(config('billing.razorpay.key'), config('billing.razorpay.secret'))->acceptJson();
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

        return $tenant->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => 'pending',
            'billing_interval' => $interval,
            'razorpay_subscription_id' => $response['id'],
            'provider_metadata' => ['short_url' => $response['short_url'] ?? null],
        ]);
    }
}
