<?php

namespace App\Services;

use App\Models\{AdminAuditLog, Announcement, Coupon, Plan, SuperAdmin, Tenant};
use App\Support\TenantContext;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\ValidationException;

class SuperAdminService
{
    public function authorize(): SuperAdmin
    {
        $admin = Auth::guard('superadmin')->user();
        abort_unless($admin instanceof SuperAdmin && $admin->fresh()?->is_active, 403);
        return $admin;
    }

    public function audit(string $action, ?Tenant $tenant = null, array $details = [], ?string $reason = null, ?string $type = null, ?int $id = null): void
    {
        AdminAuditLog::create([
            'super_admin_id' => $this->authorize()->id, 'tenant_id' => $tenant?->id,
            'action' => $action, 'details' => $details, 'reason' => $reason,
            'target_type' => $type, 'target_id' => $id, 'ip_address' => request()->ip(),
        ]);
    }

    public function withinTenant(Tenant $tenant, callable $callback): mixed
    {
        $this->authorize();
        $context = app(TenantContext::class);
        $previous = $context->current();
        $context->set($tenant);
        try { return $callback(); }
        finally { $previous ? $context->set($previous) : $context->clear(); }
    }

    public function dashboard(): array
    {
        $this->authorize();
        // Explicitly authorized platform-wide aggregate; no tenant scope is removed.
        $latest = DB::table('tenant_subscriptions')->selectRaw('MAX(id)')
            ->whereNotIn('status', ['pending', 'checkout_failed'])->groupBy('tenant_id');
        $subscriptions = DB::table('tenant_subscriptions as s')->join('tenants as t', 't.id', '=', 's.tenant_id')
            ->join('plans as p', 'p.id', '=', 's.plan_id')->whereIn('s.id', $latest)
            ->where('t.status', 'active')->select('s.*', 'p.code', 'p.monthly_price_paise', 'p.yearly_price_paise')->get();
        $active = $subscriptions->filter(fn ($s) => $s->status === 'active' && ($s->code === 'legacy' || ($s->current_period_ends_at && $s->current_period_ends_at > now())));
        $trial = $subscriptions->filter(fn ($s) => $s->status === 'trialing' && $s->trial_ends_at && $s->trial_ends_at > now())->count();
        $mrr = $active->whereNotIn('code', ['trial', 'legacy'])->whereNotNull('razorpay_subscription_id')
            ->sum(fn ($s) => $s->billing_interval === 'yearly' ? (int) $s->yearly_price_paise / 12 : (int) $s->monthly_price_paise);
        $trend = collect(range(5, 0))->map(function ($offset) {
            $month = now()->startOfMonth()->subMonths($offset);
            return ['month' => $month->format('M Y'), 'count' => Tenant::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count()];
        });
        return ['total' => Tenant::count(), 'active' => $active->count(), 'trial' => $trial,
            'expired' => $subscriptions->filter(fn ($s) => $s->status === 'expired'
                || ($s->status === 'trialing' && (! $s->trial_ends_at || $s->trial_ends_at <= now()))
                || (in_array($s->status, ['active', 'paused', 'cancelled', 'completed']) && $s->code !== 'legacy' && (! $s->current_period_ends_at || $s->current_period_ends_at <= now())))->count(),
            'suspended' => Tenant::where('status', 'suspended')->count(), 'mrr' => round($mrr), 'trend' => $trend];
    }

    public function tenantDetails(Tenant $tenant): array
    {
        return $this->withinTenant($tenant, fn () => [
            'subscription' => app(BillingService::class)->subscription($tenant),
            'usage' => app(BillingService::class)->usage($tenant),
            'owners' => $tenant->users()->wherePivot('role', 'owner')->get(['users.id', 'users.name', 'users.email']),
        ]);
    }

    public function updateTenant(Tenant $tenant, array $data): void
    {
        $this->withinTenant($tenant, function () use ($tenant, $data) {
            DB::transaction(function () use ($tenant, $data) {
                $tenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $billing = app(BillingService::class);
                $subscription = $billing->subscription($tenant);
                $before = ['status' => $tenant->status, 'trial_ends_at' => $tenant->trial_ends_at, 'plan_id' => $subscription?->plan_id];
                if ($data['action'] === 'status') {
                    $tenant->update(['status' => $data['status']]);
                } elseif ($data['action'] === 'extend_trial') {
                    abort_unless($subscription?->status === 'trialing' && ! $subscription->razorpay_subscription_id, 409, 'Only a local trial can be extended.');
                    $end = ($subscription->trial_ends_at?->isFuture() ? $subscription->trial_ends_at->copy() : now())->addDays($data['days']);
                    $subscription->update(['trial_ends_at' => $end]);
                    $tenant->update(['trial_ends_at' => $end]);
                } else {
                    // Do not shadow a provider-managed or pending subscription with a local grant.
                    abort_if($tenant->subscriptions()->whereNotNull('razorpay_subscription_id')->where('status', '!=', 'checkout_failed')
                        ->where(fn ($q) => $q->whereNotIn('status', ['cancelled', 'completed'])->orWhere('current_period_ends_at', '>', now()))->exists(), 409, 'Provider-managed plan changes await billing policy and gateway verification.');
                    $plan = Plan::whereKey($data['plan_id'])->where('is_active', true)->whereNotIn('code', ['legacy', 'trial'])->firstOrFail();
                    $tenant->subscriptions()->create([
                        'plan_id' => $plan->id, 'status' => 'active',
                        'current_period_starts_at' => now(), 'current_period_ends_at' => $data['access_until'],
                        'provider_metadata' => ['manual_grant' => true],
                    ]);
                }
                $this->audit('tenant.'.$data['action'], $tenant, ['before' => $before, 'input' => array_diff_key($data, ['reason' => true])], $data['reason']);
            });
        });
    }

    public function saveCatalog(string $kind, ?int $id, array $data): void
    {
        $this->authorize();
        $class = $this->catalogClass($kind);
        DB::transaction(function () use ($class, $kind, $id, $data) {
            $record = $id ? $class::whereKey($id)->lockForUpdate()->firstOrFail() : new $class;
            $before = $record->exists ? $record->getAttributes() : null;
            if ($kind === 'plans' && $record->exists) {
                if (in_array($record->code, ['legacy', 'trial']) && $record->code !== $data['code']) {
                    throw ValidationException::withMessages(['code' => 'System plan codes cannot be renamed.']);
                }
                // This aggregate is platform-authorized; prevents rewriting historic price contracts.
                if (DB::table('tenant_subscriptions')->where('plan_id', $record->id)->exists()) {
                    foreach (['code', 'monthly_price_paise', 'yearly_price_paise', 'razorpay_plan_monthly_id', 'razorpay_plan_yearly_id'] as $field) {
                        if ((string) $record->$field !== (string) ($data[$field] ?? null)) {
                            throw ValidationException::withMessages([$field => 'Create a new plan for price or provider changes once a plan is in use.']);
                        }
                    }
                }
            }
            $record->fill($data)->save();
            $this->audit($kind.'.saved', details: ['before' => $before, 'after' => $record->getAttributes()], type: $kind, id: $record->id);
        });
    }

    public function deleteCatalog(string $kind, int $id): void
    {
        $this->authorize();
        $class = $this->catalogClass($kind);
        DB::transaction(function () use ($class, $kind, $id) {
            $record = $class::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($kind === 'plans') {
                abort_if(in_array($record->code, ['legacy', 'trial']) || DB::table('tenant_subscriptions')->where('plan_id', $id)->exists(), 409, 'System or referenced plans cannot be deleted. Deactivate the plan instead.');
            }
            $this->audit($kind.'.deleted', details: ['before' => $record->getAttributes()], type: $kind, id: $id);
            $record->delete();
        });
    }

    public function catalogClass(string $kind): string
    {
        return match ($kind) { 'plans' => Plan::class, 'coupons' => Coupon::class, 'announcements' => Announcement::class, default => abort(404) };
    }
}
