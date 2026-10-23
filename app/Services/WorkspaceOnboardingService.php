<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class WorkspaceOnboardingService
{
    public function complete(Tenant $tenant, array $data, ?string $logoPath): void
    {
        DB::transaction(function () use ($tenant, $data, $logoPath) {
            $prefix = strtoupper($data['invoice_prefix']);
            $tenant->update([
                'name' => $data['company_name'], 'gstin' => $data['gstin'] ?? null,
                'pan' => $data['pan'] ?? null, 'state_code' => $data['state_code'] ?? null,
                'address' => $data['address'] ?? null, 'invoice_prefix' => $prefix,
                'logo_path' => $logoPath, 'onboarding_completed_at' => now(),
            ]);
            foreach ([
                'company_name' => $data['company_name'], 'company_gstin' => $data['gstin'] ?? '',
                'company_pan' => $data['pan'] ?? '', 'company_state_code' => $data['state_code'] ?? '',
                'company_address' => $data['address'] ?? '', 'invoice_prefix' => $prefix,
                'company_logo' => $logoPath ?? '',
            ] as $key => $value) {
                Setting::set($key, $value);
            }
            if (! empty($data['customer_name'])) {
                Customer::create(['name' => $data['customer_name'], 'email' => $data['customer_email'] ?? null]);
            }
            if (! empty($data['item_name'])) {
                Item::create([
                    'name' => $data['item_name'], 'code' => null, 'hsn_sac' => $data['item_hsn'] ?? null,
                    'rate' => $data['item_rate'] ?? 0, 'gst_rate' => $data['item_gst_rate'] ?? 18,
                    'unit' => 'Nos', 'type' => 'service',
                ]);
            }
        });
    }
}
