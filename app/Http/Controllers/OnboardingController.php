<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceOnboardingService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $tenant = app(TenantContext::class)->tenant();
        if ($tenant->onboarding_completed_at) return redirect()->route('dashboard');
        return view('onboarding.show', ['tenant' => $tenant]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:12'],
            'state_code' => ['nullable', 'string', 'max:5'],
            'address' => ['nullable', 'string', 'max:2000'],
            'invoice_prefix' => ['required', 'alpha_num', 'max:10'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,gif', 'max:2048'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'item_hsn' => ['nullable', 'string', 'max:20'],
            'item_rate' => ['nullable', 'numeric', 'min:0'],
            'item_gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $tenant = app(TenantContext::class)->tenant();

        $logoPath = $tenant->logo_path;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos/tenant-'.$tenant->id, 'public');
        }
        app(WorkspaceOnboardingService::class)->complete($tenant, $data, $logoPath);

        return redirect()->route('dashboard')->with('success', 'Workspace setup is complete.');
    }
}
