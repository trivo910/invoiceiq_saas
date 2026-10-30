<?php

namespace Tests\Feature;

use App\Models\{Customer, ImportBatch, ImportRow, Plan, Tenant, User};
use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Hash, Storage};
use Tests\TestCase;

class ImportUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        app(TenantContext::class)->clear();
        Storage::fake('tenant_private');
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        DB::rollBack();
        parent::tearDown();
    }

    public function test_customer_csv_is_previewed_without_creating_customers_and_reports_row_errors(): void
    {
        [$tenant, $owner] = $this->workspace('owner');
        $csv = $this->csv(
            ['Customer Name *', 'GSTIN', 'Billing State'],
            [
                ['Aarav Sample Traders', '27ABCDE1234F1Z5', 'Maharashtra'],
                ['Meera Sample Foods', 'bad-gstin', 'Karnataka'],
            ],
        );
        $response = $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('customers.import.upload'), ['file' => UploadedFile::fake()->createWithContent('customers.csv', "\xEF\xBB\xBF".$csv)]);
        $response->assertSessionHasNoErrors();

        app(TenantContext::class)->set($tenant);
        $batch = ImportBatch::latest('id')->firstOrFail();
        $response->assertRedirect(route('customers.import.preview', $batch));
        $this->assertSame('validated', $batch->status);
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(1, $batch->ready_rows);
        $this->assertSame(0, $batch->warning_rows);
        $this->assertSame(1, $batch->error_rows);
        $this->assertSame('27', ImportRow::where('import_batch_id', $batch->id)->where('row_number', 2)->firstOrFail()->payload['billing_state_code']);
        $this->assertDatabaseCount('customers', 0);

        $this->get(route('customers.import.preview', $batch))->assertOk()->assertSee('Preview only')->assertSee('GSTIN must be 15 characters');
    }

    public function test_unknown_headings_show_mapping_and_can_be_mapped_before_preview(): void
    {
        [$tenant, $owner] = $this->workspace('owner');
        $csv = $this->csv(['Name', 'Contact Number'], [['Meera Demo', '9876543210']]);
        $upload = $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('customers.import.upload'), ['file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv)])
            ->assertSessionHasNoErrors();
        app(TenantContext::class)->set($tenant);
        $batch = ImportBatch::latest('id')->firstOrFail();
        $this->get(route('customers.import.preview', $batch))->assertOk()->assertSee('Match your column headings');
        $this->post(route('customers.import.mapping', $batch), ['mapping' => ['customer_name' => '0', 'phone' => '1']])
            ->assertRedirect(route('customers.import.preview', $batch));
        app(TenantContext::class)->set($tenant);
        $batch->refresh();
        $this->assertSame('validated', $batch->status);
        $this->assertSame('9876543210', $batch->rows()->firstOrFail()->payload['phone']);
    }

    public function test_existing_records_in_another_tenant_are_not_reported_as_duplicates(): void
    {
        [$tenantA, $ownerA] = $this->workspace('owner');
        [$tenantB] = $this->workspace('owner');
        app(TenantContext::class)->set($tenantB);
        Customer::create(['name' => 'Other Workspace Customer', 'gstin' => '27ABCDE1234F1Z5']);
        app(TenantContext::class)->clear();

        $csv = $this->csv(['Customer Name *', 'GSTIN'], [['Workspace A Customer', '27ABCDE1234F1Z5']]);
        $this->actingAs($ownerA)->withSession(['current_tenant_id' => $tenantA->id])
            ->post(route('customers.import.upload'), ['file' => UploadedFile::fake()->createWithContent('customer.csv', $csv)])
            ->assertSessionHasNoErrors();
        app(TenantContext::class)->set($tenantA);
        $batch = ImportBatch::latest('id')->firstOrFail();
        $this->assertSame(1, $batch->ready_rows);
        $this->assertSame(0, $batch->warning_rows);
    }

    public function test_item_csv_validates_required_catalog_fields_without_creating_items(): void
    {
        [$tenant, $owner] = $this->workspace('owner');
        app(TenantContext::class)->set($tenant);
        $tenant->subscriptions()->create([
            'plan_id' => Plan::where('code', 'trial')->value('id'),
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(5),
        ]);
        app(TenantContext::class)->clear();
        $csv = $this->csv(
            ['Item Name *', 'Type *', 'Selling Price *', 'GST Rate (%) *', 'Unit *'],
            [['Sample Item', 'goods', '125.50', '18', 'Pcs']],
        );
        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('items.import.upload'), ['file' => UploadedFile::fake()->createWithContent('items.csv', $csv)])
            ->assertSessionHasNoErrors();

        app(TenantContext::class)->set($tenant);
        $batch = ImportBatch::latest('id')->firstOrFail();
        $this->assertSame('validated', $batch->status);
        $this->assertSame(1, $batch->ready_rows);
        $this->assertDatabaseCount('items', 0);
        $this->assertEquals(125.5, $batch->rows()->firstOrFail()->payload['selling_price']);

        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('items.import.process', $batch), ['mode' => 'import_valid'])
            ->assertRedirect(route('items.import.preview', $batch));
        app(TenantContext::class)->set($tenant);
        $this->assertDatabaseCount('items', 1);
        $this->assertSame('Sample Item', $batch->rows()->firstOrFail()->payload['item_name']);
    }

    public function test_processing_can_stop_on_issues_or_import_valid_rows_and_skips_warning_rows(): void
    {
        [$tenant, $owner] = $this->workspace('owner');
        app(TenantContext::class)->set($tenant);
        $tenant->subscriptions()->create([
            'plan_id' => Plan::where('code', 'trial')->value('id'),
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(5),
        ]);
        app(TenantContext::class)->clear();

        $csv = $this->csv(
            ['Customer Name *', 'GSTIN'],
            [
                ['Valid Import Row', '27ABCDE1234F1Z5'],
                ['Repeated Import Row', '27ABCDE1234F1Z5'],
            ],
        );
        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('customers.import.upload'), ['file' => UploadedFile::fake()->createWithContent('customers.csv', $csv)])
            ->assertSessionHasNoErrors();
        app(TenantContext::class)->set($tenant);
        $batch = ImportBatch::latest('id')->firstOrFail();
        $this->assertSame(1, $batch->ready_rows);
        $this->assertSame(1, $batch->warning_rows);

        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])
            ->from(route('customers.import.preview', $batch))
            ->post(route('customers.import.process', $batch), ['mode' => 'stop_on_issues'])
            ->assertRedirect(route('customers.import.preview', $batch))
            ->assertSessionHasErrors('process');
        $this->assertSame('validated', $batch->fresh()->status);
        $this->assertDatabaseCount('customers', 0);

        $this->post(route('customers.import.process', $batch), ['mode' => 'import_valid'])
            ->assertRedirect(route('customers.import.preview', $batch));
        app(TenantContext::class)->set($tenant);
        $batch->refresh();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(1, $batch->rows()->where('status', 'imported')->count());
        $this->assertSame(1, $batch->rows()->where('status', 'skipped')->count());
        $this->assertDatabaseCount('customers', 1);

        $this->post(route('customers.import.process', $batch), ['mode' => 'import_valid'])
            ->assertSessionHasErrors('process');
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_staff_cannot_upload_and_unsupported_file_extension_is_rejected(): void
    {
        [$tenant, $staff] = $this->workspace('staff');
        $this->actingAs($staff)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('items.import.upload'), ['file' => UploadedFile::fake()->createWithContent('items.csv', "Name\nWidget")])
            ->assertForbidden();

        [$ownerTenant, $owner] = $this->workspace('owner');
        $this->actingAs($owner)->withSession(['current_tenant_id' => $ownerTenant->id])
            ->post(route('items.import.upload'), ['file' => UploadedFile::fake()->createWithContent('items.txt', "Name\nWidget")])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('import_batches', 0);
    }

    private function workspace(string $role): array
    {
        $tenant = Tenant::create(['name' => 'Import preview workspace', 'slug' => uniqid('preview-', true), 'status' => 'active', 'onboarding_completed_at' => now()]);
        $user = User::create(['name' => ucfirst($role), 'email' => uniqid($role.'-', true).'@example.test', 'password' => Hash::make('password')]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $tenant->users()->attach($user->id, ['role' => $role]);
        return [$tenant, $user];
    }

    private function csv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) fputcsv($stream, $row);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }
}
