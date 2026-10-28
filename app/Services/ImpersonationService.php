<?php

namespace App\Services;

use App\Models\{AdminAuditLog, Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationService
{
    public function start(Request $request, Tenant $tenant, int $ownerId, string $reason): void
    {
        $admin = app(SuperAdminService::class)->authorize();
        abort_if($request->session()->has('admin_impersonation'), 409, 'Exit the current impersonation first.');
        abort_unless($tenant->status === 'active' && $tenant->onboarding_completed_at, 409, 'Only active, onboarded workspaces can be impersonated.');
        $owner = $tenant->users()->wherePivot('role', 'owner')->whereKey($ownerId)->firstOrFail();
        abort_unless($owner->hasVerifiedEmail(), 409, 'The owner must have a verified email.');
        $state = [
            'admin_id' => $admin->id, 'tenant_id' => $tenant->id, 'user_id' => $owner->id,
            'original_user_id' => Auth::guard('web')->id(),
            'original_tenant_id' => $request->session()->get('current_tenant_id'),
            'started_at' => now()->timestamp, 'expires_at' => now()->addMinutes(15)->timestamp,
            'reason' => $reason,
        ];
        app(SuperAdminService::class)->audit('impersonation.started', $tenant, ['target_user_id' => $owner->id, 'expires_at' => $state['expires_at']], $reason);
        Auth::guard('web')->login($owner);
        $request->session()->put('admin_impersonation', $state);
        $request->session()->put('current_tenant_id', $tenant->id);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
    }

    public function stop(Request $request, string $cause = 'explicit'): void
    {
        $state = $request->session()->get('admin_impersonation');
        if (! is_array($state)) return;
        // Preserve end evidence even if the actor has since been disabled or logged out.
        AdminAuditLog::create([
            'super_admin_id' => \App\Models\SuperAdmin::find($state['admin_id'])?->id,
            'tenant_id' => Tenant::find($state['tenant_id'])?->id,
            'action' => 'impersonation.ended', 'reason' => $state['reason'],
            'details' => ['target_user_id' => $state['user_id'], 'started_at' => $state['started_at'], 'ended_at' => now()->timestamp, 'cause' => $cause],
            'ip_address' => $request->ip(),
        ]);
        Auth::guard('web')->logout();
        if ($state['original_user_id']) Auth::guard('web')->loginUsingId($state['original_user_id']);
        $request->session()->forget(['admin_impersonation', 'current_tenant_id']);
        if ($state['original_tenant_id']) $request->session()->put('current_tenant_id', $state['original_tenant_id']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        app(\App\Support\TenantContext::class)->clear();
    }
}
