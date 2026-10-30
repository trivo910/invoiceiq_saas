<?php

namespace Tests\Feature;

use App\Exports\ImportTemplateExport;
use App\Models\{Tenant, User};
use App\Support\TenantContext;
use Illuminate\Support\Facades\{DB, Hash};
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ImportTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        app(TenantContext::class)->clear();
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        DB::rollBack();
        parent::tearDown();
    }

    public function test_customer_and_item_excel_templates_include_instructions_lists_examples_and_dropdowns(): void
    {
        foreach (['customers', 'items'] as $entity) {
            $path = tempnam(sys_get_temp_dir(), 'invoiceiq-template-');
            try {
                file_put_contents($path, Excel::raw(new ImportTemplateExport($entity), \Maatwebsite\Excel\Excel::XLSX));
                $book = IOFactory::load($path);
                $this->assertSame(['Data', 'Instructions', 'Lists'], array_map(fn ($sheet) => $sheet->getTitle(), $book->getAllSheets()));
                $data = $book->getSheetByName('Data');
                if ($entity === 'customers') {
                    $this->assertSame('Customer Name *', $data->getCell('A1')->getValue());
                    $this->assertSame('27', (string) $data->getCell('I2')->getValue());
                    $this->assertSame('=StateNames', $data->getCell('H4')->getDataValidation()->getFormula1());
                } else {
                    $this->assertSame('Item Name *', $data->getCell('A1')->getValue());
                    $this->assertSame('=ItemTypes', $data->getCell('E4')->getDataValidation()->getFormula1());
                    $this->assertSame('=GstRates', $data->getCell('G4')->getDataValidation()->getFormula1());
                    $this->assertSame('=UnitNames', $data->getCell('H4')->getDataValidation()->getFormula1());
                }
                $this->assertSame('Lists', $book->getNamedRange('StateNames')->getWorksheet()->getTitle());
                $this->assertSame('SAMPLE ROW: This is example data. Delete this light-grey row before uploading.', $data->getComment('A2')->getText()->getPlainText());
                $this->assertSame('A2', $data->getFreezePane());
                $book->disconnectWorksheets();
            } finally {
                @unlink($path);
            }
        }
    }

    public function test_import_template_downloads_are_role_protected_and_csv_is_utf8(): void
    {
        [$tenant, $owner] = $this->workspace('owner');
        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])->get(route('customers.index'))->assertOk()->assertSee('Download Excel Template');
        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])->get(route('items.index'))->assertOk()->assertSee('Download Excel Template');
        $response = $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])->get(route('customers.import.template'));
        $response->assertDownload('customers-import-template.xlsx');
        $csv = $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])->get(route('items.import.template.csv'));
        $csv->assertDownload('items-import-template.csv');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv->streamedContent());
        $this->assertStringContainsString('Item Name *', $csv->streamedContent());

        foreach (['admin', 'accountant'] as $role) {
            $member = User::create(['name' => ucfirst($role), 'email' => uniqid($role.'-', true).'@example.test', 'password' => Hash::make('password')]);
            $member->forceFill(['email_verified_at' => now()])->save();
            $tenant->users()->attach($member->id, ['role' => $role]);
            $this->actingAs($member)->withSession(['current_tenant_id' => $tenant->id])->get(route('items.import.template'))->assertDownload('items-import-template.xlsx');
        }

        $viewer = User::create(['name' => 'Viewer', 'email' => uniqid('viewer-', true).'@example.test', 'password' => Hash::make('password')]);
        $viewer->forceFill(['email_verified_at' => now()])->save();
        $tenant->users()->attach($viewer->id, ['role' => 'viewer']);
        $this->actingAs($viewer)->withSession(['current_tenant_id' => $tenant->id])->get(route('customers.index'))->assertOk()->assertDontSee('Download Excel Template');
        $this->actingAs($viewer)->withSession(['current_tenant_id' => $tenant->id])->get(route('customers.import.template'))->assertForbidden();

        $staff = User::create(['name' => 'Staff', 'email' => uniqid('staff-', true).'@example.test', 'password' => Hash::make('password')]);
        $staff->forceFill(['email_verified_at' => now()])->save();
        $tenant->users()->attach($staff->id, ['role' => 'staff']);
        $this->actingAs($staff)->withSession(['current_tenant_id' => $tenant->id])->get(route('items.import.template'))->assertForbidden();
    }

    private function workspace(string $role): array
    {
        $tenant = Tenant::create(['name' => 'Import template workspace', 'slug' => uniqid('import-', true), 'status' => 'active', 'onboarding_completed_at' => now()]);
        $user = User::create(['name' => ucfirst($role), 'email' => uniqid($role.'-', true).'@example.test', 'password' => Hash::make('password')]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $tenant->users()->attach($user->id, ['role' => $role]);
        return [$tenant, $user];
    }
}
