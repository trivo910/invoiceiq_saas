<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Setting;
use App\Models\User;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->orderBy('id')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        // Default admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@invoiceiq.com'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );
        $admin->tenants()->syncWithoutDetaching([$tenant->id => ['role' => 'owner']]);

        // Default settings
        $defaults = [
            'company_name'       => 'Acme Solutions Pvt. Ltd.',
            'company_gstin'      => '27AABCU9603R1ZX',
            'company_pan'        => 'AABCU9603R',
            'company_address'    => 'Plot 14, Software Technology Park',
            'company_city'       => 'Nagpur',
            'company_state'      => 'Maharashtra',
            'company_state_code' => '27',
            'company_phone'      => '9876543210',
            'company_email'      => 'billing@acmesolutions.in',
            'bank_name'          => 'HDFC Bank',
            'bank_acc_no'        => '50200012345678',
            'bank_ifsc'          => 'HDFC0001234',
            'bank_branch'        => 'Nagpur Main',
            'invoice_prefix'     => 'INV',
            'invoice_seq'        => '1',
            'default_terms'      => '30',
            'default_notes'      => 'Thank you for your business. Payment is due within 30 days of invoice date.',
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // Sample customers
        $customers = [
            ['name' => 'Rajesh Enterprises',  'gstin' => '27AAACR5055K1ZP', 'email' => 'rajesh@example.com',   'phone' => '9876543210', 'billing_city' => 'Nagpur',     'billing_state' => 'Maharashtra', 'billing_state_code' => '27', 'billing_address' => 'B-42, Civil Lines'],
            ['name' => 'TechSpark Pvt Ltd',   'gstin' => '27AABCT1234R1ZX', 'email' => 'info@techspark.in',    'phone' => '9812345678', 'billing_city' => 'Pune',       'billing_state' => 'Maharashtra', 'billing_state_code' => '27', 'billing_address' => 'IT Park, Hinjewadi'],
            ['name' => 'Sunrise Exports',     'gstin' => '29AABCS1234R1ZX', 'email' => 'ops@sunrise.co',       'phone' => '9900112233', 'billing_city' => 'Bengaluru',  'billing_state' => 'Karnataka',   'billing_state_code' => '29', 'billing_address' => 'Koramangala 5th Block'],
            ['name' => 'NextGen Media Ltd',   'gstin' => '27AABCN9876R1ZX', 'email' => 'contact@nextgen.com',  'phone' => '9988776655', 'billing_city' => 'Mumbai',     'billing_state' => 'Maharashtra', 'billing_state_code' => '27', 'billing_address' => 'Andheri West'],
            ['name' => 'Delta Pharma',        'gstin' => '24AABCD1234R1ZX', 'email' => 'accounts@delta.in',    'phone' => '9911223344', 'billing_city' => 'Ahmedabad',  'billing_state' => 'Gujarat',     'billing_state_code' => '24', 'billing_address' => 'Satellite Road'],
        ];

        foreach ($customers as $c) {
            Customer::updateOrCreate(['gstin' => $c['gstin']], $c + ['payment_terms' => 30]);
        }

        // Sample items
        $items = [
            ['name' => 'Web Design',         'hsn_sac' => '998314', 'rate' => 25000, 'gst_rate' => 18, 'unit' => 'Nos',   'type' => 'service', 'description' => 'UI/UX website design'],
            ['name' => 'Web Development',    'hsn_sac' => '998315', 'rate' => 50000, 'gst_rate' => 18, 'unit' => 'Nos',   'type' => 'service', 'description' => 'Full-stack web development'],
            ['name' => 'SEO Services',       'hsn_sac' => '998361', 'rate' => 8000,  'gst_rate' => 18, 'unit' => 'Month', 'type' => 'service', 'description' => 'Monthly SEO retainer'],
            ['name' => 'Logo Design',        'hsn_sac' => '998391', 'rate' => 5000,  'gst_rate' => 18, 'unit' => 'Nos',   'type' => 'service', 'description' => 'Brand identity & logo design'],
            ['name' => 'Annual Maintenance', 'hsn_sac' => '998732', 'rate' => 12000, 'gst_rate' => 18, 'unit' => 'Year',  'type' => 'service', 'description' => 'Website maintenance contract'],
            ['name' => 'Digital Marketing',  'hsn_sac' => '998364', 'rate' => 15000, 'gst_rate' => 18, 'unit' => 'Month', 'type' => 'service', 'description' => 'Google & Meta ad campaigns'],
            ['name' => 'Content Writing',    'hsn_sac' => '998392', 'rate' => 3000,  'gst_rate' => 18, 'unit' => 'Month', 'type' => 'service', 'description' => 'Blog & web copywriting'],
            ['name' => 'Laptop',             'hsn_sac' => '8471',   'rate' => 65000, 'gst_rate' => 18, 'unit' => 'Pcs',   'type' => 'goods',   'description' => 'Laptop computer'],
            ['name' => 'Printer Paper',      'hsn_sac' => '4802',   'rate' => 350,   'gst_rate' => 12, 'unit' => 'Ream',  'type' => 'goods',   'description' => 'A4 size 500 sheets'],
        ];

        foreach ($items as $item) {
            Item::updateOrCreate(['name' => $item['name']], $item);
        }
    }
}
