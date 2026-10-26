<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use RuntimeException;

class BillingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            throw new RuntimeException('Billing demo values can only be seeded in local or testing environments.');
        }

        $plans = [
            'trial' => [
                'name' => 'Free trial (Demo)', 'description' => 'DEMO ONLY. 14-day evaluation plan.',
                'monthly_price_paise' => 0, 'yearly_price_paise' => null,
                'monthly_invoice_limit' => 20, 'member_limit' => 2,
                'gst_reports' => true, 'custom_branding' => false, 'api_access' => false,
                'is_active' => true, 'sort_order' => 1,
            ],
            'starter' => [
                'name' => 'Starter (Demo)', 'description' => 'DEMO ONLY. Temporary sample price and limits.',
                'monthly_price_paise' => 49900, 'yearly_price_paise' => 499000,
                'monthly_invoice_limit' => 100, 'member_limit' => 3,
                'gst_reports' => true, 'custom_branding' => false, 'api_access' => false,
                'is_active' => true, 'sort_order' => 2,
            ],
            'pro' => [
                'name' => 'Pro (Demo)', 'description' => 'DEMO ONLY. Temporary sample price and limits.',
                'monthly_price_paise' => 149900, 'yearly_price_paise' => 1499000,
                'monthly_invoice_limit' => 500, 'member_limit' => 10,
                'gst_reports' => true, 'custom_branding' => true, 'api_access' => false,
                'is_active' => true, 'sort_order' => 3,
            ],
            'business' => [
                'name' => 'Business (Demo)', 'description' => 'DEMO ONLY. Temporary sample price and limits.',
                'monthly_price_paise' => 399900, 'yearly_price_paise' => 3999000,
                'monthly_invoice_limit' => null, 'member_limit' => 25,
                'gst_reports' => true, 'custom_branding' => true, 'api_access' => true,
                'is_active' => true, 'sort_order' => 4,
            ],
        ];

        foreach ($plans as $code => $values) {
            Plan::where('code', $code)->update($values);
        }
    }
}
