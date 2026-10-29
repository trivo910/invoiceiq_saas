<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\{Auth, DB};

class WorkspaceLifecycle
{
    public function trash(Tenant $tenant, string $reason, bool $platform = false): void
    {
        if ($platform) app(SuperAdminService::class)->authorize();
        else abort_unless($tenant->users()->whereKey(Auth::guard('web')->id())->wherePivot('role', 'owner')->exists(), 403);
        $operation = function () use ($tenant, $reason, $platform) {
            DB::transaction(function () use ($tenant, $reason, $platform) {
                $tenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                abort_if($tenant->subscriptions()->whereNotNull('razorpay_subscription_id')->where('status', '!=', 'checkout_failed')
                    ->where(fn ($q) => $q->whereNotIn('status', ['cancelled', 'completed'])->orWhere('current_period_ends_at', '>', now()))->exists(), 409, 'Resolve provider subscriptions before deleting this workspace.');
                if ($platform) app(SuperAdminService::class)->audit('tenant.deleted', $tenant, reason: $reason);
                else app(TenantAudit::class)->record('workspace.deleted', 'Tenant', $tenant->id, ['reason' => $reason]);
                $tenant->delete();
            });
        };
        if ($platform) app(SuperAdminService::class)->withinTenant($tenant, $operation);
        else {
            abort_unless(app(\App\Support\TenantContext::class)->id() === $tenant->id, 403);
            $operation();
        }
    }

    public function restore(int $id, string $reason): void
    {
        $admin = app(SuperAdminService::class);
        $admin->authorize();
        DB::transaction(function () use ($admin, $id, $reason) {
            $tenant = Tenant::onlyTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
            $tenant->restore();
            $admin->audit('tenant.restored', $tenant, reason: $reason);
        });
    }
}
