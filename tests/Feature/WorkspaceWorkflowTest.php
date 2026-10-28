<?php

namespace Tests\Feature;

use App\Jobs\{BuildAccountExport, SendInvoice};
use App\Mail\InvoiceMail;
use App\Models\{AccountExport, Customer, Invoice, InvoiceDelivery, InvoiceItem, InvoiceShareLink, Item, Payment, Plan, Setting, Tenant, User};
use App\Services\{InvoicePdf, TenantInvoiceMailer, TenantWork, WorkspaceExport, WorkspaceWorkflow};
use App\Support\TenantContext;
use Illuminate\Support\Facades\{DB, Mail, Queue, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkspaceWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        Storage::fake('tenant_private');
        app(TenantContext::class)->clear();
    }
    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        DB::rollBack();
        parent::tearDown();
    }

    public function test_public_link_stores_only_a_hash_and_is_revocable_read_only_and_tenant_bound(): void
    {
        [$a, $owner, $invoice] = $this->workspace('Alpha');
        [$b, $other] = $this->workspace('Beta');
        $this->asTenant($owner, $a)->post(route('invoices.share', $invoice), ['days' => 7])->assertRedirect()->assertSessionHas('invoice_share_url');
        $url = session('invoice_share_url');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $link = DB::table('invoice_share_links')->where('tenant_id', $a->id)->first();
        $this->assertNotSame($token, $link->token_hash);
        $this->assertSame(hash('sha256', $token), $link->token_hash);
        $this->asTenant($other, $b)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertNull(app(TenantContext::class)->current());
        $this->delete(route('invoices.share.revoke', [$invoice->id, $link->id]))->assertNotFound();
        $this->post($url, [])->assertStatus(405);
        $this->get(route('invoices.public', str_repeat('0', 64)))->assertNotFound();
        $this->asTenant($owner, $a)->delete(route('invoices.share.revoke', [$invoice->id, $link->id]))->assertRedirect();
        $this->get($url)->assertNotFound();
    }

    public function test_public_links_expire_and_hide_draft_cancelled_or_suspended_records(): void
    {
        [$tenant, $owner, $invoice] = $this->workspace('Expiry');
        app(TenantContext::class)->set($tenant);
        $url = app(WorkspaceWorkflow::class)->share($invoice, $owner->id, 1);
        app(TenantContext::class)->clear();
        $this->travel(2)->days(); $this->get($url)->assertNotFound(); $this->travelBack();
        app(TenantContext::class)->set($tenant); $invoice->update(['status' => 'draft']); app(TenantContext::class)->clear();
        $this->asTenant($owner, $tenant)->post(route('invoices.share', $invoice), ['days' => 7])->assertStatus(422);
        $this->get($url)->assertNotFound();
        app(TenantContext::class)->set($tenant); $invoice->update(['status' => 'cancelled']); app(TenantContext::class)->clear();
        $this->get($url)->assertNotFound();
        $tenant->update(['status' => 'suspended']); $this->get($url)->assertNotFound();
    }

    public function test_workflow_pages_and_mutations_enforce_role_tenant_and_expired_subscription(): void
    {
        [$tenant, $owner, $invoice] = $this->workspace('Roles');
        $this->asTenant($owner, $tenant)->get(route('invoices.activity', $invoice))->assertOk();
        $this->get(route('exports.index'))->assertOk();
        $tenant->users()->updateExistingPivot($owner->id, ['role' => 'viewer']);
        $this->post(route('invoices.share', $invoice), ['days' => 7])->assertForbidden();
        $this->get(route('exports.csv', 'invoices'))->assertForbidden();
        $tenant->users()->updateExistingPivot($owner->id, ['role' => 'owner']);
        DB::table('tenant_subscriptions')->where('tenant_id', $tenant->id)->update(['trial_ends_at' => now()->subDay()]);
        $this->post(route('invoices.share', $invoice), ['days' => 7])->assertRedirect(route('billing.index'));
        $this->get(route('exports.csv', 'invoices'))->assertOk();
    }

    public function test_mail_is_queued_with_ids_and_encrypted_payload_then_worker_restores_context(): void
    {
        Queue::fake(); Mail::fake();
        [$a, $owner, $invoice] = $this->workspace('MailA');
        [$b] = $this->workspace('MailB');
        $data = ['to_email' => 'recipient@example.test', 'email_subject' => 'Invoice subject', 'email_body' => 'PRIVATE MESSAGE'];
        $this->asTenant($owner, $a)->post(route('invoices.email', $invoice), $data)->assertRedirect();
        $row = DB::table('invoice_deliveries')->where('tenant_id', $a->id)->first();
        $this->assertStringNotContainsString('PRIVATE MESSAGE', $row->payload);
        Queue::assertPushed(SendInvoice::class, fn ($job) => $job->tenantId === $a->id && $job->deliveryId === $row->id);
        $job = new SendInvoice($a->id, $row->id);
        $this->assertStringNotContainsString('recipient@example.test', serialize($job));
        app(TenantContext::class)->set($b);
        $job->handle(app(TenantInvoiceMailer::class));
        $job->handle(app(TenantInvoiceMailer::class));
        $this->assertSame($b->id, app(TenantContext::class)->id());
        Mail::assertSent(InvoiceMail::class, fn ($mail) => $mail->invoice->tenant_id === $a->id && $mail->hasTo('recipient@example.test'));
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('invoice_deliveries', ['tenant_id' => $a->id, 'id' => $row->id, 'status' => 'sent']);
    }

    public function test_mail_failure_does_not_mark_invoice_sent_and_failed_hook_is_tenant_scoped(): void
    {
        [$a, $owner, $invoice] = $this->workspace('FailureA');
        [$b] = $this->workspace('FailureB');
        app(TenantContext::class)->set($a);
        $invoice->update(['status' => 'draft']);
        $delivery = InvoiceDelivery::create(['invoice_id' => $invoice->id, 'requested_by' => $owner->id, 'payload' => ['to_email' => 'someone@example.test', 'email_subject' => 'Subject', 'email_body' => 'Body']]);
        app(TenantContext::class)->set($b);
        $mailer = $this->createMock(TenantInvoiceMailer::class);
        $mailer->method('send')->willThrowException(new \RuntimeException('Sensitive transport error'));
        $job = new SendInvoice($a->id, $delivery->id);
        try { $job->handle($mailer); $this->fail('Transport failure must be retryable.'); }
        catch (\RuntimeException $e) { $this->assertStringNotContainsString('Sensitive', $e->getMessage()); }
        $this->assertSame($b->id, app(TenantContext::class)->id());
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'tenant_id' => $a->id, 'status' => 'draft']);
        (new SendInvoice($b->id, $delivery->id))->failed(null);
        $this->assertDatabaseHas('invoice_deliveries', ['id' => $delivery->id, 'status' => 'queued']);
        $job->failed(null);
        $this->assertDatabaseHas('invoice_deliveries', ['id' => $delivery->id, 'status' => 'failed']);
    }

    public function test_mail_worker_rechecks_membership_before_sending(): void
    {
        Mail::fake();
        [$tenant, $owner, $invoice] = $this->workspace('Revoked sender');
        app(TenantContext::class)->set($tenant);
        $delivery = InvoiceDelivery::create(['invoice_id' => $invoice->id, 'requested_by' => $owner->id, 'payload' => ['to_email' => 'someone@example.test', 'email_subject' => 'Subject', 'email_body' => 'Body']]);
        app(TenantContext::class)->clear();
        $tenant->users()->updateExistingPivot($owner->id, ['role' => 'viewer']);
        (new SendInvoice($tenant->id, $delivery->id))->handle(app(TenantInvoiceMailer::class));
        Mail::assertNothingSent();
        $this->assertDatabaseHas('invoice_deliveries', ['id' => $delivery->id, 'status' => 'failed']);
    }

    public function test_csv_stream_restores_context_and_neutralizes_formula_cells(): void
    {
        [$a, $owner] = $this->workspace('CSV-ALPHA');
        [$b] = $this->workspace('CSV-BETA');
        app(TenantContext::class)->set($a);
        Customer::create(['name' => '=HYPERLINK("test")']);
        app(TenantContext::class)->clear();
        $response = $this->asTenant($owner, $a)->get(route('exports.csv', 'customers'))->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('CSV-ALPHA', $csv);
        $this->assertStringNotContainsString('CSV-BETA', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_full_export_is_owner_only_private_tenant_scoped_and_excludes_secrets(): void
    {
        Queue::fake();
        [$a, $owner] = $this->workspace('EXPORT-ALPHA');
        [$b, $other] = $this->workspace('EXPORT-BETA');
        $this->asTenant($owner, $a)->post(route('exports.account'))->assertRedirect();
        $row = DB::table('account_exports')->where('tenant_id', $a->id)->first();
        Queue::assertPushed(BuildAccountExport::class, fn ($job) => $job->tenantId === $a->id && $job->exportId === $row->id);
        app(TenantContext::class)->set($b);
        $job = new BuildAccountExport($a->id, $row->id);
        $job->handle(app(WorkspaceExport::class));
        $this->assertSame($b->id, app(TenantContext::class)->id());
        $ready = DB::table('account_exports')->where('tenant_id', $a->id)->where('id', $row->id)->first();
        $this->assertStringStartsWith('tenants/'.$a->id.'/exports/', $ready->path);
        $zip = new \ZipArchive(); $zip->open(Storage::disk('tenant_private')->path($ready->path));
        $manifest = $zip->getFromName('workspace.json');
        $this->assertStringNotContainsString('SMTP-SECRET', $manifest);
        $this->assertStringNotContainsString('EXPORT-BETA', $manifest);
        $this->assertStringContainsString('EXPORT-ALPHA', $zip->getFromName('invoices.csv'));
        $this->assertStringNotContainsString('EXPORT-BETA', $zip->getFromName('invoices.csv'));
        $zip->close();
        $this->asTenant($other, $b)->get(route('exports.download', $row->id))->assertNotFound();
        $this->asTenant($owner, $a)->get(route('exports.download', $row->id))->assertOk();
        $a->users()->updateExistingPivot($owner->id, ['role' => 'admin']);
        $this->post(route('exports.account'))->assertForbidden();
        $this->get(route('exports.download', $row->id))->assertForbidden();
    }

    public function test_export_expiry_pruning_preserves_other_tenant_files(): void
    {
        [$a, $owner] = $this->workspace('PruneA');
        [$b, $other] = $this->workspace('PruneB');
        $paths = [];
        foreach ([[$a, $owner, true], [$b, $other, false]] as [$tenant, $user, $expired]) {
            app(TenantContext::class)->set($tenant);
            $path = 'tenants/'.$tenant->id.'/exports/'.Str::uuid().'.zip';
            Storage::disk('tenant_private')->put($path, 'archive');
            AccountExport::create(['requested_by' => $user->id, 'status' => 'ready', 'path' => $path, 'expires_at' => $expired ? now()->subMinute() : now()->addDay()]);
            $paths[] = $path;
        }
        app(TenantContext::class)->clear();
        $this->asTenant($owner, $a)->get(route('exports.download', DB::table('account_exports')->where('tenant_id', $a->id)->value('id')))->assertNotFound();
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('workflows:prune'));
        Storage::disk('tenant_private')->assertMissing($paths[0]);
        Storage::disk('tenant_private')->assertExists($paths[1]);
    }

    public function test_workflow_model_links_reject_foreign_invoices(): void
    {
        [$a, $owner] = $this->workspace('GuardA');
        [, , $foreign] = $this->workspace('GuardB');
        app(TenantContext::class)->set($a);
        $this->expectException(ValidationException::class);
        InvoiceDelivery::create(['invoice_id' => $foreign->id, 'requested_by' => $owner->id, 'payload' => []]);
    }

    public function test_branding_is_validated_entitled_and_uses_same_pdf_for_delivery(): void
    {
        [$tenant, $owner, $invoice] = $this->workspace('Branding');
        Plan::where('code', 'trial')->update(['custom_branding' => true]);
        $this->asTenant($owner, $tenant)->post(route('settings.branding'), ['invoice_pdf_template' => '../../secret', 'brand_color' => 'bad'])->assertSessionHasErrors(['invoice_pdf_template', 'brand_color']);
        $this->post(route('settings.branding'), ['invoice_pdf_template' => 'accent', 'brand_color' => '#123456'])->assertRedirect();
        app(TenantContext::class)->set($tenant);
        $pdf = app(InvoicePdf::class)->make($invoice)->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $mail = (new InvoiceMail($invoice, 'Subject', 'Body'))->build();
        $this->assertCount(1, $mail->rawAttachments);
        app(TenantContext::class)->clear();
        Plan::where('code', 'trial')->update(['custom_branding' => false]);
        $this->post(route('settings.branding'), ['invoice_pdf_template' => 'accent', 'brand_color' => '#123456'])->assertRedirect(route('billing.index'));
    }

    public function test_foreign_job_id_cannot_read_or_send_another_tenants_delivery(): void
    {
        Mail::fake();
        [$a, $owner, $invoice] = $this->workspace('Foreign job A');
        [$b] = $this->workspace('Foreign job B');
        app(TenantContext::class)->set($a);
        $delivery = InvoiceDelivery::create(['invoice_id' => $invoice->id, 'requested_by' => $owner->id, 'payload' => ['to_email' => 'someone@example.test', 'email_subject' => 'Subject', 'email_body' => 'Body']]);
        app(TenantContext::class)->set($a);
        try {
            (new SendInvoice($b->id, $delivery->id))->handle(app(TenantInvoiceMailer::class));
            $this->fail('Foreign delivery IDs must not resolve.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->assertSame($a->id, app(TenantContext::class)->id());
        }
        Mail::assertNothingSent();
        $this->assertDatabaseHas('invoice_deliveries', ['id' => $delivery->id, 'tenant_id' => $a->id, 'status' => 'queued']);
    }

    public function test_export_worker_rechecks_ownership_and_no_file_is_built_after_revocation(): void
    {
        [$tenant, $owner] = $this->workspace('Export revocation');
        app(TenantContext::class)->set($tenant);
        $export = AccountExport::create(['requested_by' => $owner->id]);
        app(TenantContext::class)->clear();
        $tenant->users()->updateExistingPivot($owner->id, ['role' => 'viewer']);
        (new BuildAccountExport($tenant->id, $export->id))->handle(app(WorkspaceExport::class));
        $this->assertDatabaseHas('account_exports', ['id' => $export->id, 'status' => 'failed', 'path' => null]);
        $this->assertSame([], Storage::disk('tenant_private')->allFiles('tenants/'.$tenant->id.'/exports'));
        $this->assertNull(app(TenantContext::class)->id());
    }

    private function asTenant(User $user, Tenant $tenant): self
    {
        return $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id]);
    }

    private function workspace(string $name): array
    {
        $tenant = Tenant::create(['name' => $name, 'slug' => Str::uuid(), 'status' => 'active', 'onboarding_completed_at' => now()]);
        $owner = User::create(['name' => 'Owner', 'email' => Str::uuid().'@example.test', 'password' => 'password', 'role' => 'admin']);
        $owner->forceFill(['email_verified_at' => now()])->save();
        $tenant->users()->attach($owner->id, ['role' => 'owner']);
        app(TenantContext::class)->set($tenant);
        $tenant->subscriptions()->create(['plan_id' => Plan::where('code', 'trial')->value('id'), 'status' => 'trialing', 'trial_ends_at' => now()->addDays(14)]);
        Setting::set('company_name', $name); Setting::set('email_enabled', '1'); Setting::set('smtp_password', 'SMTP-SECRET');
        $customer = Customer::create(['name' => $name.' Customer', 'email' => 'customer@example.test']);
        $item = Item::create(['name' => $name.' Item', 'rate' => 100, 'unit' => 'Nos']);
        $invoice = Invoice::create(['number' => $name.'-INV', 'status' => 'sent', 'invoice_date' => now()->toDateString(), 'customer_id' => $customer->id, 'customer_name' => $customer->name, 'sub_total' => 100, 'total_tax' => 18, 'grand_total' => 118]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'item_id' => $item->id, 'item_name' => $item->name, 'qty' => 1, 'rate' => 100, 'taxable_amount' => 100, 'total_tax' => 18, 'total_amount' => 118]);
        Payment::create(['invoice_id' => $invoice->id, 'amount' => 10, 'payment_date' => now()->toDateString(), 'mode' => 'cash']);
        app(TenantContext::class)->clear();
        return [$tenant, $owner, $invoice];
    }
}
