<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnforceImpersonation
{
    public function handle(Request $request, Closure $next)
    {
        $state = $request->session()->get('admin_impersonation');
        if (! is_array($state)) return $next($request);
        $admin = Auth::guard('superadmin')->user();
        $tenant = Tenant::find($state['tenant_id']);
        $valid = $admin && $admin->id === $state['admin_id'] && $admin->fresh()?->is_active
            && $state['expires_at'] > now()->timestamp
            && Auth::guard('web')->id() === $state['user_id']
            && $tenant?->status === 'active'
            && $tenant->users()->whereKey($state['user_id'])->wherePivot('role', 'owner')->exists();
        if (! $valid) {
            app(ImpersonationService::class)->stop($request, 'expired_or_revoked');
            return redirect()->route('superadmin.dashboard')->with('warning', 'Impersonation ended or expired.');
        }
        // Bind the support session to one workspace even if another valid membership exists.
        $request->session()->put('current_tenant_id', $state['tenant_id']);
        if ($request->routeIs('superadmin.impersonation.stop', 'superadmin.logout')) return $next($request);
        $allowed = ['home', 'dashboard', 'invoices.index', 'invoices.pdf', 'invoices.number-preview', 'customers.index', 'customers.search', 'items.index', 'items.search', 'reports.gst', 'reports.gst.export', 'settings.logo.image'];
        abort_unless(in_array($request->method(), ['GET', 'HEAD']) && in_array($request->route()?->getName(), $allowed, true), 403, 'Impersonation is read-only. Exit it to access administration, billing, or account settings.');
        return $next($request);
    }
}
