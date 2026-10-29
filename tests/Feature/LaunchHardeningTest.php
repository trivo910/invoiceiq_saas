<?php

namespace Tests\Feature;

use App\Models\{AdminAuditLog, Plan, Setting, SuperAdmin, Tenant, TenantAuditLog, User};
use App\Support\TenantContext;
use Illuminate\Support\Facades\{Artisan, Auth, DB, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;

class LaunchHardeningTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); DB::beginTransaction(); app(TenantContext::class)->clear(); }
    protected function tearDown(): void { app(TenantContext::class)->clear(); DB::rollBack(); parent::tearDown(); }

    public function test_public_pages_are_rendered_and_legal_copy_is_marked_draft(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Create invoices');
        $this->get(route('pricing'))->assertOk()->assertSee('Pricing is not yet approved');
        $this->get(route('legal', 'terms'))->assertOk()->assertSee('SAMPLE DRAFT')->assertSee('NOT APPROVED');
        $this->get(route('legal', 'privacy'))->assertOk()->assertSee('Privacy notice');
        $this->get('/legal/impossible')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_authenticated_pages_send_security_headers_and_no_store(): void
    {
        [$tenant, $owner] = $this->workspace();
        $response = $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])->get(route('workspace.activity'));
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('Workspace activity');
    }

    public function test_workspace_audit_stores_field_names_not_sensitive_values_and_is_append_only(): void
    {
        [$tenant, $owner] = $this->workspace();
        app(TenantContext::class)->set($tenant);
        Setting::set('smtp_password', 'do-not-log-this');
        $row = TenantAuditLog::where('target_type', 'Setting')->latest('id')->firstOrFail();
        $this->assertSame(['key', 'value'], $row->details['fields']);
        $this->assertStringNotContainsString('do-not-log-this', json_encode($row->details));
        $this->expectException(\LogicException::class);
        $row->delete();
    }

    public function test_smtp_password_is_encrypted_at_rest_and_decrypted_for_settings(): void
    {
        [$tenant] = $this->workspace();
        app(TenantContext::class)->set($tenant);
        Setting::set('smtp_password', 'private-smtp-value');
        $raw = DB::table('settings')->where('tenant_id', $tenant->id)->where('key', 'smtp_password')->value('value');
        $this->assertStringStartsWith('enc:v1:', $raw);
        $this->assertStringNotContainsString('private-smtp-value', $raw);
        $this->assertSame('private-smtp-value', Setting::get('smtp_password'));
    }

    public function test_legacy_smtp_password_encryption_command_migrates_without_outputting_secret(): void
    {
        [$tenant] = $this->workspace();
        DB::table('settings')->insert(['tenant_id' => $tenant->id, 'key' => 'smtp_password', 'value' => 'legacy-secret-value', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(0, Artisan::call('settings:encrypt-smtp-passwords'));
        $raw = DB::table('settings')->where('tenant_id', $tenant->id)->where('key', 'smtp_password')->value('value');
        $this->assertStringStartsWith('enc:v1:', $raw);
        $this->assertStringNotContainsString('legacy-secret-value', $raw);
        $this->assertStringNotContainsString('legacy-secret-value', Artisan::output());
        app(TenantContext::class)->set($tenant);
        $this->assertSame('legacy-secret-value', Setting::get('smtp_password'));
    }

    public function test_platform_admin_can_restore_a_soft_deleted_workspace_with_separate_audit(): void
    {
        [$tenant, $owner] = $this->workspace();
        $admin = SuperAdmin::create(['name' => 'Platform operator', 'email' => Str::uuid().'@example.test', 'password' => 'platform-password']);
        Auth::guard('superadmin')->login($admin);
        Auth::shouldUse('web');
        app(\App\Services\WorkspaceLifecycle::class)->trash($tenant, 'approved support deletion', true);
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        app(\App\Services\WorkspaceLifecycle::class)->restore($tenant->id, 'owner requested restoration');
        $this->assertNotSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'tenant.restored', 'tenant_id' => $tenant->id]);
    }

    public function test_owner_can_delete_workspace_only_after_password_and_exact_name_confirmation(): void
    {
        [$tenant, $owner] = $this->workspace();
        $url = route('workspace.delete');
        $data = ['password' => 'owner-password', 'confirmation' => $tenant->name, 'reason' => 'Workspace no longer needed'];
        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])->post($url, array_merge($data, ['password' => 'wrong']))->assertUnprocessable();
        $this->assertNotSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])->post($url, $data)->assertRedirect(route('home'));
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseHas('tenant_audit_logs', ['tenant_id' => $tenant->id, 'action' => 'workspace.deleted']);
    }

    private function workspace(): array
    {
        $tenant = Tenant::create(['name' => 'Launch workspace', 'slug' => (string) Str::uuid(), 'status' => 'active', 'onboarding_completed_at' => now()]);
        $owner = User::create(['name' => 'Owner', 'email' => Str::uuid().'@example.test', 'password' => Hash::make('owner-password'), 'role' => 'admin']);
        $owner->forceFill(['email_verified_at' => now()])->save();
        $tenant->users()->attach($owner->id, ['role' => 'owner']);
        app(TenantContext::class)->set($tenant);
        $tenant->subscriptions()->create(['plan_id' => Plan::where('code', 'trial')->value('id'), 'status' => 'trialing', 'trial_ends_at' => now()->addDays(10)]);
        app(TenantContext::class)->clear();
        return [$tenant, $owner];
    }
}
