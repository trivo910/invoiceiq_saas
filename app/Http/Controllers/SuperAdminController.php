<?php

namespace App\Http\Controllers;

use App\Http\Requests\SuperAdmin\{CatalogRequest, TenantActionRequest};
use App\Models\{AdminAuditLog, Plan, Tenant};
use App\Services\{ImpersonationService, SuperAdminService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperAdminController extends Controller
{
    public function login()
    {
        return Auth::guard('superadmin')->check() ? redirect()->route('superadmin.dashboard') : view('superadmin.login');
    }

    public function authenticate(Request $request, SuperAdminService $service)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::guard('superadmin')->attempt($data + ['is_active' => true])) {
            AdminAuditLog::create(['action' => 'login.failed', 'ip_address' => $request->ip()]);
            return back()->withErrors(['email' => 'These credentials do not match an active super-admin account.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $service->audit('login.succeeded');
        return redirect()->route('superadmin.dashboard');
    }

    public function logout(Request $request, SuperAdminService $service, ImpersonationService $impersonation)
    {
        $impersonation->stop($request, 'admin_logout');
        $service->audit('logout');
        Auth::guard('superadmin')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        return redirect()->route('superadmin.login');
    }

    public function dashboard(SuperAdminService $service)
    {
        return view('superadmin.dashboard', ['stats' => $service->dashboard()]);
    }

    public function tenants(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,suspended']]);
        $tenants = Tenant::query()->when($data['q'] ?? null, fn ($q, $value) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$value.'%')->orWhere('slug', 'like', '%'.$value.'%')))
            ->when($data['status'] ?? null, fn ($q, $value) => $q->where('status', $value))->latest('id')->paginate(25)->withQueryString();
        return view('superadmin.tenants', compact('tenants'));
    }

    public function tenant(Tenant $tenant, SuperAdminService $service)
    {
        return view('superadmin.tenant', ['tenant' => $tenant, 'plans' => Plan::where('is_active', true)->whereNotIn('code', ['legacy', 'trial'])->get()] + $service->tenantDetails($tenant));
    }

    public function updateTenant(TenantActionRequest $request, Tenant $tenant, SuperAdminService $service)
    {
        $service->updateTenant($tenant, $request->validated());
        return back()->with('success', 'Workspace updated and action audited.');
    }

    public function impersonate(Request $request, Tenant $tenant, ImpersonationService $service)
    {
        $data = $request->validate(['owner_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->start($request, $tenant, $data['owner_id'], $data['reason']);
        return redirect()->route('dashboard');
    }

    public function stopImpersonation(Request $request, ImpersonationService $service)
    {
        $service->stop($request);
        return redirect()->route('superadmin.dashboard')->with('success', 'Impersonation ended.');
    }

    public function catalog(string $kind, SuperAdminService $service)
    {
        $class = $service->catalogClass($kind);
        return view('superadmin.catalog', ['kind' => $kind, 'records' => $class::orderBy('id')->paginate(20)]);
    }

    public function saveCatalog(CatalogRequest $request, string $kind, SuperAdminService $service, ?int $record = null)
    {
        $service->saveCatalog($kind, $record, $request->validated());
        return back()->with('success', 'Saved and audited.');
    }

    public function deleteCatalog(string $kind, int $record, SuperAdminService $service)
    {
        $service->deleteCatalog($kind, $record);
        return back()->with('success', 'Deleted and audited.');
    }

    public function audit()
    {
        return view('superadmin.audit', ['logs' => AdminAuditLog::orderByDesc('id')->paginate(50)]);
    }
}
