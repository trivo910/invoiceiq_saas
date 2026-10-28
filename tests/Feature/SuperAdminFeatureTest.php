<?php

namespace Tests\Feature;

use App\Models\{AdminAuditLog, Announcement, Coupon, Plan, SuperAdmin, Tenant, TenantSubscription, User};
use App\Services\{BillingService, SuperAdminService};
use App\Support\TenantContext;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminFeatureTest extends TestCase
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

    public function test_tenant_roles_cannot_read_or_mutate_platform_controls(): void
    {
        [$tenant, $user] = $this->workspace();
        foreach (['owner', 'admin', 'accountant', 'staff', 'viewer'] as $role) {
            $tenant->users()->updateExistingPivot($user->id, ['role' => $role]);
            $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id]);
            $this->get(route('superadmin.dashboard'))->assertForbidden();
            $this->patch(route('superadmin.tenant.update', $tenant), ['action' => 'status', 'status' => 'suspended', 'reason' => 'Unauthorized suspension'])->assertForbidden();
            $this->post(route('superadmin.catalog.create', 'plans'), $this->planData())->assertForbidden();
        }
        $this->assertSame('active', $tenant->fresh()->status);
        $this->assertDatabaseMissing('plans', ['code' => 'security-test-plan']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'access.denied']);
    }

    public function test_superadmin_login_is_separate_from_tenant_credentials_and_rotates_session(): void
    {
        [, $user] = $this->workspace();
        $admin = $this->admin($user->email);
        $this->post(route('superadmin.authenticate'), ['email' => $user->email, 'password' => 'tenant-password'])->assertSessionHasErrors('email');
        $this->assertGuest('superadmin');
        $this->post(route('superadmin.authenticate'), ['email' => $admin->email, 'password' => 'platform-password'])->assertRedirect(route('superadmin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'superadmin');
        $this->assertGuest('web');
        $this->get(route('superadmin.dashboard'))->assertOk()->assertSee('Catalog MRR estimate');
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('superadmin.logout'))->assertRedirect(route('superadmin.login'));
        $this->assertGuest('superadmin');
        $this->assertDatabaseHas('admin_audit_logs', ['super_admin_id' => $admin->id, 'action' => 'login.succeeded']);
        $this->assertDatabaseHas('admin_audit_logs', ['super_admin_id' => $admin->id, 'action' => 'logout']);
    }

    public function test_disabled_platform_admin_cannot_login_or_keep_an_active_session(): void
    {
        $admin = $this->admin();
        $admin->update(['is_active' => false]);
        $this->post(route('superadmin.authenticate'), ['email' => $admin->email, 'password' => 'platform-password'])->assertSessionHasErrors('email');
        $this->asAdmin($admin)->get(route('superadmin.dashboard'))->assertForbidden();
    }

    public function test_superadmin_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('superadmin.authenticate'), ['email' => 'missing@example.test', 'password' => 'incorrect'])->assertRedirect();
        }
        $this->post(route('superadmin.authenticate'), ['email' => 'missing@example.test', 'password' => 'incorrect'])->assertStatus(429);
    }

    public function test_suspension_is_audited_and_blocks_only_the_selected_workspace(): void
    {
        [$a, $ownerA] = $this->workspace();
        [$b, $ownerB] = $this->workspace();
        $admin = $this->admin();
        $this->asAdmin($admin)->get(route('superadmin.tenants', ['q' => $a->slug]))->assertOk()->assertSee($a->slug)->assertDontSee($b->slug);
        $this->get(route('superadmin.tenant', $a))->assertOk();
        $this->patch(route('superadmin.tenant.update', $a), ['action' => 'status', 'status' => 'suspended', 'reason' => 'Requested account suspension'])->assertRedirect();
        $this->assertDatabaseHas('admin_audit_logs', ['super_admin_id' => $admin->id, 'tenant_id' => $a->id, 'action' => 'tenant.status', 'reason' => 'Requested account suspension']);
        $this->assertFalse(app(BillingService::class)->canWrite($a->fresh()));
        $this->actingAs($ownerA)->withSession(['current_tenant_id' => $a->id])->get(route('dashboard'))->assertForbidden();
        $this->actingAs($ownerB)->withSession(['current_tenant_id' => $b->id])->get(route('dashboard'))->assertOk();
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_trial_extension_restores_previous_tenant_context_and_rejects_missing_reason(): void
    {
        [$a, , $sub] = $this->workspace();
        [$b] = $this->workspace();
        $this->asAdmin($this->admin());
        $this->patch(route('superadmin.tenant.update', $a), ['action' => 'extend_trial', 'days' => 10])->assertSessionHasErrors('reason');
        $before = $sub->trial_ends_at->copy();
        app(TenantContext::class)->set($b);
        app(SuperAdminService::class)->updateTenant($a, ['action' => 'extend_trial', 'days' => 10, 'reason' => 'Support approved trial extension']);
        $this->assertSame($b->id, app(TenantContext::class)->id());
        $savedEnd = DB::table('tenant_subscriptions')->where('tenant_id', $a->id)->where('id', $sub->id)->value('trial_ends_at');
        $this->assertSame($before->addDays(10)->timestamp, \Illuminate\Support\Carbon::parse($savedEnd)->timestamp);
    }

    public function test_manual_grants_are_local_and_provider_managed_changes_are_rejected(): void
    {
        [$a] = $this->workspace();
        [$b, , $provider] = $this->workspace();
        app(TenantContext::class)->set($b);
        $provider->update(['razorpay_subscription_id' => 'sub_security_test', 'status' => 'active', 'current_period_ends_at' => now()->addMonth()]);
        app(TenantContext::class)->clear();
        $plan = Plan::create($this->planData());
        $this->asAdmin($this->admin());
        $data = ['action' => 'plan', 'plan_id' => $plan->id, 'access_until' => now()->addMonth()->toDateTimeString(), 'reason' => 'Approved local access grant'];
        $this->patch(route('superadmin.tenant.update', $a), $data)->assertRedirect();
        $this->assertDatabaseHas('tenant_subscriptions', ['tenant_id' => $a->id, 'plan_id' => $plan->id, 'status' => 'active', 'razorpay_subscription_id' => null]);
        $this->patch(route('superadmin.tenant.update', $b), $data)->assertStatus(409);
        $this->assertDatabaseMissing('tenant_subscriptions', ['tenant_id' => $b->id, 'plan_id' => $plan->id]);
    }

    public function test_plan_crud_preserves_system_and_referenced_plan_contracts(): void
    {
        [$tenant, , $subscription] = $this->workspace();
        $this->asAdmin($this->admin());
        $this->post(route('superadmin.catalog.create', 'plans'), $this->planData())->assertRedirect();
        $plan = Plan::where('code', 'security-test-plan')->firstOrFail();
        $this->get(route('superadmin.catalog', 'plans'))->assertOk()->assertSee('Security Test Plan');
        app(TenantContext::class)->set($tenant);
        $subscription->update(['plan_id' => $plan->id]);
        app(TenantContext::class)->clear();
        $this->put(route('superadmin.catalog.update', ['plans', $plan->id]), $this->planData() + ['extra' => 'ignored'])->assertRedirect();
        $changed = $this->planData(); $changed['monthly_price_paise']++;
        $this->put(route('superadmin.catalog.update', ['plans', $plan->id]), $changed)->assertSessionHasErrors('monthly_price_paise');
        $this->delete(route('superadmin.catalog.delete', ['plans', $plan->id]))->assertStatus(409);
        $this->delete(route('superadmin.catalog.delete', ['plans', Plan::where('code', 'trial')->value('id')]))->assertStatus(409);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'plans.saved', 'target_id' => $plan->id]);
    }

    public function test_coupon_validation_and_announcement_visibility_and_escaping(): void
    {
        [$tenant, $owner] = $this->workspace();
        $this->asAdmin($this->admin());
        $this->post(route('superadmin.catalog.create', 'coupons'), ['code' => 'save20', 'type' => 'percent', 'value' => 120, 'is_active' => 1])->assertSessionHasErrors('value');
        $this->post(route('superadmin.catalog.create', 'coupons'), ['code' => 'save20', 'type' => 'percent', 'value' => 20, 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('coupons', ['code' => 'SAVE20', 'value' => 20]);
        $this->post(route('superadmin.catalog.create', 'announcements'), ['title' => 'Platform notice', 'body' => '<script>alert(1)</script>', 'is_active' => 1])->assertRedirect();
        Announcement::create(['title' => 'Future-only notice', 'body' => 'Not yet visible', 'is_active' => true, 'starts_at' => now()->addDay()]);
        Announcement::create(['title' => 'Expired-only notice', 'body' => 'Already ended', 'is_active' => true, 'expires_at' => now()->subMinute()]);
        $this->get(route('superadmin.catalog', 'coupons'))->assertOk()->assertSee('Coupon catalog only');
        $this->get(route('superadmin.catalog', 'announcements'))->assertOk();
        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])->get(route('dashboard'))
            ->assertOk()->assertSee('Platform notice')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('Future-only notice')->assertDontSee('Expired-only notice');
    }

    public function test_impersonation_requires_target_owner_and_is_read_only_and_audited(): void
    {
        [$a, $ownerA] = $this->workspace();
        [$b, $ownerB] = $this->workspace();
        $admin = $this->admin();
        $this->asAdmin($admin);
        $this->post(route('superadmin.impersonate', $a), ['owner_id' => $ownerB->id, 'reason' => 'Investigate customer issue'])->assertNotFound();
        $this->post(route('superadmin.impersonate', $a), ['owner_id' => $ownerA->id, 'reason' => 'Investigate customer issue'])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Read-only support impersonation')->assertSee('Investigate customer issue');
        $this->withSession(['current_tenant_id' => $b->id])->get(route('dashboard'))->assertOk()->assertSessionHas('current_tenant_id', $a->id);
        $this->post(route('customers.store'), ['name' => 'Blocked write'])->assertForbidden();
        $this->get(route('settings.index'))->assertForbidden();
        $this->get(route('billing.index'))->assertForbidden();
        $this->get(route('superadmin.dashboard'))->assertForbidden();
        $this->post(route('superadmin.impersonation.stop'))->assertRedirect(route('superadmin.dashboard'))->assertSessionMissing('admin_impersonation');
        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'superadmin');
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'impersonation.started', 'tenant_id' => $a->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'impersonation.ended', 'tenant_id' => $a->id]);
        $this->get(route('superadmin.audit'))->assertOk()->assertSee('impersonation.ended');
    }

    public function test_impersonation_expiry_restores_original_identity_and_workspace(): void
    {
        [$a, $original] = $this->workspace();
        [$b, $owner] = $this->workspace();
        $this->actingAs($original)->withSession(['current_tenant_id' => $a->id]);
        $this->asAdmin($this->admin())->post(route('superadmin.impersonate', $b), ['owner_id' => $owner->id, 'reason' => 'Investigate an expiry issue'])->assertRedirect();
        $this->travel(16)->minutes();
        $this->get(route('dashboard'))->assertRedirect(route('superadmin.dashboard'))->assertSessionMissing('admin_impersonation')->assertSessionHas('current_tenant_id', $a->id);
        $this->assertAuthenticatedAs($original, 'web');
        $this->travelBack();
    }

    public function test_direct_platform_service_requires_a_platform_identity(): void
    {
        [, $owner] = $this->workspace();
        $this->actingAs($owner);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(SuperAdminService::class)->dashboard();
    }

    public function test_impersonation_ends_when_owner_membership_is_revoked(): void
    {
        [$tenant, $owner] = $this->workspace();
        $this->asAdmin($this->admin())->post(route('superadmin.impersonate', $tenant), ['owner_id' => $owner->id, 'reason' => 'Inspect membership revocation'])->assertRedirect();
        $tenant->users()->updateExistingPivot($owner->id, ['role' => 'viewer']);
        $this->get(route('dashboard'))->assertRedirect(route('superadmin.dashboard'))->assertSessionMissing('admin_impersonation');
        $this->assertGuest('web');
        $this->assertDatabaseHas('admin_audit_logs', ['tenant_id' => $tenant->id, 'action' => 'impersonation.ended']);
    }

    public function test_dashboard_mrr_counts_latest_provider_period_and_excludes_manual_grants(): void
    {
        $this->asAdmin($this->admin());
        $baseline = app(SuperAdminService::class)->dashboard()['mrr'];
        $plan = Plan::create($this->planData());
        foreach (['monthly', 'yearly', 'manual', 'ended', 'suspended'] as $case) {
            [$tenant, , $sub] = $this->workspace();
            app(TenantContext::class)->set($tenant);
            $sub->update([
                'plan_id' => $plan->id, 'status' => 'active',
                'billing_interval' => $case === 'yearly' ? 'yearly' : 'monthly',
                'razorpay_subscription_id' => $case === 'manual' ? null : 'sub_'.Str::random(12),
                'current_period_ends_at' => $case === 'ended' ? now()->subDay() : now()->addMonth(),
            ]);
            if ($case === 'suspended') $tenant->update(['status' => 'suspended']);
        }
        app(TenantContext::class)->clear();
        $stats = app(SuperAdminService::class)->dashboard();
        $this->assertEquals($baseline + 10000 + round(100000 / 12), $stats['mrr']);
        $this->assertCount(6, $stats['trend']);
    }

    public function test_audit_records_are_immutable_and_catalog_deletion_is_audited(): void
    {
        $this->asAdmin($this->admin());
        $this->post(route('superadmin.catalog.create', 'coupons'), ['code' => 'DELETE-ME', 'type' => 'fixed_paise', 'value' => 100, 'is_active' => 0])->assertRedirect();
        $coupon = Coupon::where('code', 'DELETE-ME')->firstOrFail();
        $this->delete(route('superadmin.catalog.delete', ['coupons', $coupon->id]))->assertRedirect();
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
        $log = AdminAuditLog::where('action', 'coupons.deleted')->where('target_id', $coupon->id)->firstOrFail();
        $this->expectException(\LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    private function admin(?string $email = null): SuperAdmin
    {
        return SuperAdmin::create(['name' => 'Platform operator', 'email' => $email ?? Str::uuid().'@example.test', 'password' => 'platform-password']);
    }

    private function asAdmin(SuperAdmin $admin): self
    {
        $this->actingAs($admin, 'superadmin');
        Auth::shouldUse('web');
        return $this;
    }

    private function workspace(): array
    {
        $tenant = Tenant::create(['name' => 'Security Workspace '.Str::random(6), 'slug' => 'sa-'.Str::uuid(), 'status' => 'active', 'onboarding_completed_at' => now()]);
        $owner = User::create(['name' => 'Workspace owner', 'email' => Str::uuid().'@example.test', 'password' => 'tenant-password', 'role' => 'admin']);
        $owner->forceFill(['email_verified_at' => now()])->save();
        $tenant->users()->attach($owner->id, ['role' => 'owner']);
        app(TenantContext::class)->set($tenant);
        $subscription = TenantSubscription::create(['plan_id' => Plan::where('code', 'trial')->value('id'), 'status' => 'trialing', 'trial_ends_at' => now()->addDays(10)]);
        app(TenantContext::class)->clear();
        return [$tenant, $owner, $subscription];
    }

    private function planData(): array
    {
        return ['code' => 'security-test-plan', 'name' => 'Security Test Plan', 'monthly_price_paise' => 10000, 'yearly_price_paise' => 100000, 'monthly_invoice_limit' => 100, 'member_limit' => 3, 'gst_reports' => true, 'custom_branding' => false, 'api_access' => false, 'is_active' => true, 'sort_order' => 10];
    }
}
