<?php

namespace App\Http\Controllers;

use App\Models\{AccountExport, InvoiceDelivery, InvoiceShareLink, TenantAuditLog};
use App\Services\{BillingService, WorkspaceLifecycle};
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class WorkspaceSafetyController extends Controller
{
    public function activity()
    {
        $tenant = app(TenantContext::class)->current();
        return view('workflows.activity', ['events' => TenantAuditLog::latest('id')->paginate(30), 'tenant' => $tenant,
            'usage' => app(BillingService::class)->usage($tenant),
            'queued' => InvoiceDelivery::where('status', 'queued')->count(), 'failed' => InvoiceDelivery::where('status', 'failed')->count(),
            'links' => InvoiceShareLink::whereNull('revoked_at')->where('expires_at', '>', now())->count(),
            'exports' => AccountExport::where('status', 'ready')->where('expires_at', '>', now())->count()]);
    }
    public function delete(Request $request, WorkspaceLifecycle $lifecycle)
    {
        $data = $request->validate(['password' => ['required', 'string'], 'confirmation' => ['required', 'string'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $tenant = app(TenantContext::class)->current();
        abort_unless(Hash::check($data['password'], $request->user()->password), 422, 'Password confirmation failed.');
        abort_unless(hash_equals($tenant->name, $data['confirmation']), 422, 'Type the workspace name exactly.');
        $lifecycle->trash($tenant, $data['reason']);
        $request->session()->forget('current_tenant_id');
        Auth::guard('web')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        return redirect()->route('home')->with('success', 'Workspace moved to deleted workspaces. Contact platform support for recovery.');
    }
}
