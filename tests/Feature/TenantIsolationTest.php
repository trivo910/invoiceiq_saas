<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLogoStorage;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->context = app(TenantContext::class);
        $this->context->clear();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_dashboard_search_and_invoice_route_are_tenant_scoped(): void
    {
        [$tenantA, $userA, $dataA] = $this->workspace('Alpha', 'alpha@example.test', 'INV-ALPHA-ONLY');
        [$tenantB, $userB, $dataB] = $this->workspace('Beta', 'beta@example.test', 'INV-BETA-ONLY');

        $this->asTenant($userA, $tenantA)->get(route('dashboard'))
            ->assertOk()->assertViewHas('stats', fn (array $stats) => (int) $stats['invoice_count'] === 1 && (float) $stats['total_invoiced'] === 100.0);
        $this->asTenant($userB, $tenantB)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => (int) $stats['invoice_count'] === 1 && (float) $stats['total_invoiced'] === 200.0)
            ->assertViewHas('recent', fn ($recent) => $recent->pluck('number')->all() === ['INV-BETA-ONLY']);

        $this->asTenant($userB, $tenantB)->get(route('customers.search', ['q' => 'Shared']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $dataB['customer']->id)->assertJsonFragment(['name' => 'Shared customer']);
        $this->asTenant($userB, $tenantB)->get(route('items.search', ['q' => 'Shared']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $dataB['item']->id)->assertJsonFragment(['name' => 'Shared item']);
        $this->asTenant($userB, $tenantB)->get(route('invoices.edit', $dataA['invoice']->id))->assertNotFound();
        $this->asTenant($userA, $tenantA)->get(route('invoices.edit', $dataB['invoice']->id))->assertNotFound();
        $this->asTenant($userB, $tenantB)->put(route('customers.update', $dataA['customer']->id), ['name' => 'Tampered'])->assertNotFound();
        $this->asTenant($userB, $tenantB)->put(route('items.update', $dataA['item']->id), ['name' => 'Tampered'])->assertNotFound();
        $this->asTenant($userB, $tenantB)->post(route('invoices.payment', $dataA['invoice']->id), [
            'amount' => 5, 'payment_date' => '2026-10-12', 'mode' => 'cash',
        ])->assertNotFound();
    }

    public function test_invoice_submission_rejects_foreign_customer_and_item_ids(): void
    {
        [, , $foreign] = $this->workspace('Alpha', 'alpha@example.test', 'INV-ALPHA');
        [$tenantB, $userB] = $this->workspace('Beta', 'beta@example.test', 'INV-BETA');
        $this->asTenant($userB, $tenantB)->post(route('invoices.store'), [
            'number' => 'INV-TAMPERED', 'invoice_date' => '2026-10-01',
            'customer_id' => $foreign['customer']->id, 'customer_name' => 'Foreign customer',
            'rows' => [[
                'item_id' => $foreign['item']->id, 'item_name' => 'Foreign item',
                'qty' => 1, 'rate' => 10, 'gst_rate' => 18,
            ]],
        ])->assertSessionHasErrors(['customer_id', 'rows.0.item_id']);

        $this->context->set($tenantB);
        $this->assertSame(0, Invoice::where('number', 'INV-TAMPERED')->count());
    }

    public function test_workspaces_can_have_the_same_invoice_number_gstin_and_item_code(): void
    {
        [$tenantA, , $dataA] = $this->workspace('Alpha', 'alpha@example.test', 'INV-DUPLICATE');
        [$tenantB, , $dataB] = $this->workspace('Beta', 'beta@example.test', 'INV-DUPLICATE');

        $this->assertNotSame($dataA['invoice']->id, $dataB['invoice']->id);
        $this->assertSame($dataA['customer']->gstin, $dataB['customer']->gstin);
        $this->assertSame($dataA['item']->code, $dataB['item']->code);
        $this->context->set($tenantA);
        $this->assertSame(1, Invoice::where('number', 'INV-DUPLICATE')->count());
        $this->context->set($tenantB);
        $this->assertSame(1, Invoice::where('number', 'INV-DUPLICATE')->count());
    }

    public function test_invoice_lines_and_payments_cannot_link_to_another_tenants_invoice(): void
    {
        [, , $foreign] = $this->workspace('Alpha', 'alpha@example.test', 'INV-ALPHA');
        [$tenantB] = $this->workspace('Beta', 'beta@example.test', 'INV-BETA');
        $this->context->set($tenantB);

        try {
            Payment::create(['invoice_id' => $foreign['invoice']->id, 'amount' => 1, 'payment_date' => '2026-10-01', 'mode' => 'cash']);
            $this->fail('Cross-tenant payment should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_id', $exception->errors());
        }

        try {
            InvoiceItem::create(['invoice_id' => $foreign['invoice']->id, 'item_id' => null, 'item_name' => 'Bad line', 'qty' => 1, 'rate' => 1]);
            $this->fail('Cross-tenant invoice line should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_id', $exception->errors());
        }

        $this->assertSame(0, Payment::where('invoice_id', $foreign['invoice']->id)->count());
        $this->assertSame(0, InvoiceItem::where('invoice_id', $foreign['invoice']->id)->count());
    }

    public function test_gst_report_and_excel_export_contain_only_the_active_tenant(): void
    {
        $this->workspace('Alpha', 'alpha@example.test', 'INV-ALPHA-ONLY');
        [$tenantB, $userB] = $this->workspace('Beta', 'beta@example.test', 'INV-BETA-ONLY');

        $this->asTenant($userB, $tenantB)->get(route('reports.gst', [
            'report' => 'gstr1', 'period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31',
        ]))->assertOk()->assertViewHas('data', function (array $data): bool {
            return $data['totals']['invoices'] === 1
                && $data['b2b'][0]['number'] === 'INV-BETA-ONLY'
                && collect($data['b2c'])->pluck('number')->doesntContain('INV-ALPHA-ONLY');
        });

        $this->asTenant($userB, $tenantB)->get(route('reports.gst', [
            'report' => 'hsn', 'period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31',
        ]))->assertOk()->assertViewHas('data', function (array $data): bool {
            return $data['rows']->count() === 1
                && (float) $data['rows']->first()->total_taxable === 100.0
                && (float) $data['rows']->first()->total_qty === 1.0;
        });

        $response = $this->asTenant($userB, $tenantB)->get(route('reports.gst.export', [
            'report' => 'gstr1', 'period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31', 'format' => 'excel',
        ]))->assertOk()->assertDownload();

        $temporary = tempnam(sys_get_temp_dir(), 'invoiceiq-export-');
        try {
            file_put_contents($temporary, file_get_contents($response->baseResponse->getFile()->getPathname()));
            $rows = IOFactory::load($temporary)->getActiveSheet()->toArray();
            $exportText = implode(' ', array_map(fn ($row) => implode(' ', array_map('strval', $row)), $rows));
            $this->assertStringContainsString('INV-BETA-ONLY', $exportText);
            $this->assertStringNotContainsString('INV-ALPHA-ONLY', $exportText);
        } finally {
            @unlink($temporary);
        }
    }

    public function test_private_logo_route_and_storage_reject_another_tenants_path(): void
    {
        Storage::fake('tenant_private');
        [$tenantA, $userA] = $this->workspace('Alpha', 'alpha@example.test', 'INV-A');
        $pathA = "tenants/{$tenantA->id}/logos/a.png";
        Storage::disk('tenant_private')->put($pathA, 'tenant-a-logo');
        $this->context->set($tenantA);
        Setting::set('company_logo', $pathA);

        [$tenantB, $userB] = $this->workspace('Beta', 'beta@example.test', 'INV-B');
        $pathB = "tenants/{$tenantB->id}/logos/b.png";
        Storage::disk('tenant_private')->put($pathB, 'tenant-b-logo');
        $this->context->set($tenantB);
        Setting::set('company_logo', $pathB);

        $this->asTenant($userB, $tenantB)->get(route('settings.logo.image'))
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent('tenant-b-logo');
        $this->assertNull(app(TenantLogoStorage::class)->contents($pathA, $tenantB->id));
        $this->asTenant($userA, $tenantA)->get(route('settings.logo.image'))
            ->assertOk()->assertContent('tenant-a-logo');
    }

    public function test_legacy_shared_logo_files_are_moved_to_each_referencing_tenants_private_path(): void
    {
        Storage::fake('public');
        Storage::fake('tenant_private');
        [$tenantA] = $this->workspace('Alpha', 'alpha@example.test', 'INV-A');
        $legacyPath = 'logos/company_logo.png';
        Storage::disk('public')->put($legacyPath, 'shared-legacy-logo');
        $this->context->set($tenantA);
        Setting::set('company_logo', $legacyPath);

        [$tenantB] = $this->workspace('Beta', 'beta@example.test', 'INV-B');
        $this->context->set($tenantB);
        Setting::set('company_logo', $legacyPath);

        $this->assertSame('shared-legacy-logo', app(TenantLogoStorage::class)->contents($legacyPath, $tenantB->id));
        $this->assertSame($legacyPath, DB::table('settings')->where('tenant_id', $tenantA->id)->where('key', 'company_logo')->value('value'));
        $this->assertSame(1, app(TenantLogoStorage::class)->migrateAllLegacyFiles());
        $this->assertStringStartsWith("tenants/{$tenantA->id}/logos/", (string) DB::table('settings')->where('tenant_id', $tenantA->id)->where('key', 'company_logo')->value('value'));
        $this->assertStringStartsWith("tenants/{$tenantB->id}/logos/", (string) DB::table('settings')->where('tenant_id', $tenantB->id)->where('key', 'company_logo')->value('value'));
        Storage::disk('public')->assertMissing($legacyPath);

        [$tenantC] = $this->workspace('Gamma', 'gamma@example.test', 'INV-C');
        $tenantLegacyPath = "logos/tenant-{$tenantC->id}/legacy.png";
        Storage::disk('public')->put($tenantLegacyPath, 'tenant-c-old-logo');
        $this->context->set($tenantC);
        Setting::set('company_logo', $tenantLegacyPath);
        $this->assertSame('tenant-c-old-logo', app(TenantLogoStorage::class)->contents($tenantLegacyPath, $tenantC->id));
        $this->assertStringStartsWith("tenants/{$tenantC->id}/logos/", (string) DB::table('settings')->where('tenant_id', $tenantC->id)->where('key', 'company_logo')->value('value'));
        Storage::disk('public')->assertMissing($tenantLegacyPath);
    }

    private function workspace(string $name, string $email, string $invoiceNumber): array
    {
        $tenant = Tenant::create([
            'name' => $name, 'slug' => strtolower($name).'-'.uniqid(), 'status' => 'active',
            'onboarding_completed_at' => now(),
        ]);
        $user = User::create([
            'name' => $name.' owner', 'email' => $email, 'password' => 'password',
            'role' => 'admin', 'email_verified_at' => now(),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        $this->context->set($tenant);
        $customer = Customer::create(['name' => 'Shared customer', 'email' => $email, 'gstin' => '27ABCDE1234F1Z5']);
        $item = Item::create(['name' => 'Shared item', 'code' => 'SHARED-CODE', 'rate' => 100, 'gst_rate' => 18, 'unit' => 'Nos', 'type' => 'service']);
        $invoice = Invoice::create([
            'number' => $invoiceNumber, 'status' => 'sent', 'invoice_date' => '2026-10-10',
            'customer_id' => $customer->id, 'customer_name' => $customer->name, 'customer_gstin' => $customer->gstin,
            'sub_total' => 100, 'total_tax' => 18, 'grand_total' => $name === 'Beta' ? 200 : 100,
            'amount_paid' => 0,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_id' => $item->id, 'item_name' => $item->name,
            'hsn_sac' => '998314', 'qty' => 1, 'rate' => 100, 'gst_rate' => 18,
            'taxable_amount' => 100, 'total_tax' => 18, 'total_amount' => 118,
        ]);
        Payment::create(['invoice_id' => $invoice->id, 'amount' => 10, 'payment_date' => '2026-10-11', 'mode' => 'cash']);

        return [$tenant, $user, compact('customer', 'item', 'invoice')];
    }

    private function asTenant(User $user, Tenant $tenant): static
    {
        return $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id]);
    }
}
