<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\BillingService;
use App\Services\WorkspaceRegistrationService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingFeatureTest extends TestCase
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
        $this->context->clear();
        DB::rollBack();
        parent::tearDown();
    }

    public function test_non_owner_plan_restrictions_return_to_dashboard_and_reject_json_writes(): void
    {
        [$tenant, $user, $subscription] = $this->workspace('Restricted staff', 'restricted-staff@example.test', now()->subMinute(), 0);
        $tenant->users()->updateExistingPivot($user->id, ['role' => 'staff']);
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id]);

        $this->post(route('invoices.store'), [])->assertRedirect(route('dashboard'))
            ->assertSessionHas('warning', 'This workspace is read-only. Contact your workspace owner to restore access.');
        $this->get(route('dashboard'))->assertOk()->assertSee('Contact your workspace owner to restore access.');
        $this->postJson(route('invoices.store'), [])->assertForbidden()
            ->assertJsonPath('message', 'This workspace is read-only. Contact your workspace owner to restore access.');

        $this->context->set($tenant);
        $subscription->update(['trial_ends_at' => now()->addDay()]);
        $this->post(route('invoices.store'), [])->assertRedirect(route('dashboard'))->assertSessionHas('warning');
        $this->postJson(route('invoices.store'), [])->assertForbidden()
            ->assertJsonPath('message', 'Invoices limit reached for your plan. Contact your workspace owner to restore access.');

        Plan::where('code', 'trial')->update(['gst_reports' => false]);
        $this->get(route('reports.gst'))->assertRedirect(route('dashboard'))->assertSessionHas('warning');
        $this->getJson(route('reports.gst'))->assertForbidden()
            ->assertJsonPath('message', 'This feature is not included in your configured plan. Contact your workspace owner to restore access.');
        $this->get(route('billing.index'))->assertForbidden();
    }

    public function test_new_workspace_receives_a_fourteen_day_trial(): void
    {
        $user = app(WorkspaceRegistrationService::class)->register([
            'name' => 'Trial Owner', 'email' => 'trial@example.test', 'workspace' => 'Trial Workspace', 'password' => 'password',
        ]);
        $tenant = $user->tenants()->firstOrFail();
        $subscription = DB::table('tenant_subscriptions')->where('tenant_id', $tenant->id)->first();

        $this->assertSame('trialing', $subscription->status);
        $this->assertSame('trial', Plan::find($subscription->plan_id)->code);
        $this->assertTrue($tenant->trial_ends_at->between(now()->addDays(13)->subMinute(), now()->addDays(14)->addMinute()));
    }

    public function test_workspace_registration_rejects_malformed_or_negative_trial_duration(): void
    {
        foreach (['invalid', -1] as $index => $trialDays) {
            config(['billing.trial_days' => $trialDays]);
            $email = "invalid-trial-config-{$index}@example.test";

            try {
                app(WorkspaceRegistrationService::class)->register([
                    'name' => 'Invalid Trial Config',
                    'email' => $email,
                    'workspace' => 'Invalid Trial Config',
                    'password' => 'password',
                ]);
                $this->fail('Invalid trial configuration should prevent workspace registration.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame('BILLING_TRIAL_DAYS must be a non-negative integer.', $exception->getMessage());
            }

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }

        config(['billing.trial_days' => 0]);
        $user = app(WorkspaceRegistrationService::class)->register([
            'name' => 'No Trial Owner', 'email' => 'no-trial@example.test',
            'workspace' => 'No Trial Workspace', 'password' => 'password',
        ]);
        $tenant = $user->tenants()->firstOrFail();
        $subscription = DB::table('tenant_subscriptions')->where('tenant_id', $tenant->id)->first();
        $this->assertSame(now()->timestamp, $tenant->trial_ends_at->timestamp);
        $this->assertSame('trialing', $subscription->status);
    }

    public function test_active_paid_subscription_without_period_end_is_read_only_but_legacy_remains_unlimited(): void
    {
        [$paidTenant, $paidUser, $paidSubscription] = $this->workspace('Missing paid period', 'missing-paid-period@example.test', now()->addDays(10), null);
        $this->context->set($paidTenant);
        $paidSubscription->update(['plan_id' => Plan::where('code', 'starter')->value('id'), 'status' => 'active', 'current_period_ends_at' => null]);
        $billing = app(BillingService::class);

        $this->assertFalse($billing->canWrite($paidTenant));
        $this->actingAs($paidUser)->withSession(['current_tenant_id' => $paidTenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('Subscription ended')->assertSee('This workspace is read-only');

        [$legacyTenant, , $legacySubscription] = $this->workspace('Legacy access', 'legacy-access@example.test', null, null);
        $this->context->set($legacyTenant);
        $legacySubscription->update(['plan_id' => Plan::where('code', 'legacy')->value('id'), 'status' => 'active', 'current_period_ends_at' => null]);
        $this->assertTrue($billing->canWrite($legacyTenant));
    }

    public function test_ended_paid_subscription_can_start_replacement_checkout_but_current_and_legacy_cannot(): void
    {
        [$endedTenant, , $endedSubscription] = $this->workspace('Ended paid plan', 'ended-paid@example.test', now()->addDays(10), null);
        $this->context->set($endedTenant);
        $endedSubscription->update([
            'plan_id' => Plan::where('code', 'starter')->value('id'),
            'status' => 'active',
            'current_period_ends_at' => now()->subSecond(),
        ]);

        [$currentTenant, , $currentSubscription] = $this->workspace('Current paid plan', 'current-paid@example.test', now()->addDays(10), null);
        $this->context->set($currentTenant);
        $currentSubscription->update([
            'plan_id' => Plan::where('code', 'starter')->value('id'),
            'status' => 'active',
            'current_period_ends_at' => now()->addDay(),
        ]);

        [$legacyTenant, , $legacySubscription] = $this->workspace('Legacy checkout block', 'legacy-checkout@example.test', null, null);
        $this->context->set($legacyTenant);
        $legacySubscription->update([
            'plan_id' => Plan::where('code', 'legacy')->value('id'),
            'status' => 'active',
            'current_period_ends_at' => null,
        ]);

        $billing = app(BillingService::class);
        $this->context->set($endedTenant);
        $this->assertTrue($billing->canStartCheckout($endedTenant));
        $this->context->set($currentTenant);
        $this->assertFalse($billing->canStartCheckout($currentTenant));
        $this->context->set($legacyTenant);
        $this->assertTrue($billing->canStartCheckout($legacyTenant));
    }

    public function test_paused_subscription_can_start_replacement_checkout_only_after_paid_period_ends(): void
    {
        [$currentTenant, , $currentSubscription] = $this->workspace('Current paused plan', 'current-paused@example.test', now()->addDays(10), null);
        $this->context->set($currentTenant);
        $currentSubscription->update([
            'plan_id' => Plan::where('code', 'starter')->value('id'),
            'status' => 'paused',
            'current_period_ends_at' => now()->addDay(),
        ]);

        [$endedTenant, , $endedSubscription] = $this->workspace('Ended paused plan', 'ended-paused@example.test', now()->addDays(10), null);
        $this->context->set($endedTenant);
        $endedSubscription->update([
            'plan_id' => Plan::where('code', 'starter')->value('id'),
            'status' => 'paused',
            'current_period_ends_at' => now()->subSecond(),
        ]);

        $billing = app(BillingService::class);
        $this->context->set($currentTenant);
        $this->assertTrue($billing->canWrite($currentTenant));
        $this->assertFalse($billing->canStartCheckout($currentTenant));
        $this->context->set($endedTenant);
        $this->assertFalse($billing->canWrite($endedTenant));
        $this->assertTrue($billing->canStartCheckout($endedTenant));
    }

    public function test_expired_trial_keeps_reads_but_blocks_writes(): void
    {
        [$tenant, $user] = $this->workspace('Expired Trial', 'expired@example.test', now()->subMinute(), null);
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('Trial expired')->assertSee('This workspace is read-only');
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('settings.index'))->assertOk();
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('users.index'))->assertOk();
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->post(route('invoices.store'), [])->assertRedirect(route('billing.index'))->assertSessionHas('billing_read_only');
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('users.store'), ['email' => 'blocked-invite@example.test', 'role' => 'staff'])
            ->assertRedirect(route('billing.index'))->assertSessionHas('billing_read_only');
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('settings.update'), [])
            ->assertRedirect(route('billing.index'))->assertSessionHas('billing_read_only');
        $this->assertDatabaseMissing('tenant_invitations', ['tenant_id' => $tenant->id, 'email' => 'blocked-invite@example.test']);
    }

    public function test_read_only_workspace_cannot_accept_an_invitation(): void
    {
        [$tenant, $owner] = $this->workspace('Read-only invite acceptance', 'readonly-invite@example.test', now()->subMinute(), null);
        $plainToken = 'readonly-invitation-token';
        $invite = TenantInvitation::create([
            'tenant_id' => $tenant->id, 'invited_by' => $owner->id, 'email' => 'blocked-member@example.test',
            'role' => 'staff', 'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDays(3),
        ]);

        $this->post(route('invitations.accept.store', $plainToken), [
            'name' => 'Blocked member', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertForbidden();

        $this->assertNull($invite->fresh()->accepted_at);
        $this->assertDatabaseMissing('users', ['email' => 'blocked-member@example.test']);
        $this->assertSame(1, $tenant->users()->count());
    }

    public function test_cancelled_subscription_keeps_writes_until_period_end_then_shows_read_only(): void
    {
        [$tenant, $user, $subscription] = $this->workspace('Cancelled access', 'cancelled-access@example.test', now()->addDays(10), null, 'sub_cancelled_access');
        $this->context->set($tenant);
        $subscription->update(['status' => 'active']);
        $periodEnd = now()->addDay();
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);

        $this->sendSignedWebhook('evt_cancelled_access', [
            'event' => 'subscription.cancelled', 'created_at' => now()->timestamp,
            'payload' => ['subscription' => ['entity' => ['id' => 'sub_cancelled_access', 'current_end' => $periodEnd->timestamp]]],
        ])->assertNoContent();

        $this->context->set($tenant);
        $billing = app(BillingService::class);
        $this->assertTrue($billing->canWrite($tenant));
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('Cancelled (access through period end)')->assertSee('Access through');

        $this->context->set($tenant);
        $subscription->refresh()->update(['current_period_ends_at' => now()->subSecond()]);
        $this->assertFalse($billing->canWrite($tenant));
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('Cancelled')->assertSee('This workspace is read-only');
    }

    public function test_completed_subscription_uses_provider_period_end_and_then_becomes_read_only(): void
    {
        [$tenant, $user, $subscription] = $this->workspace('Completed access', 'completed-access@example.test', now()->addDays(10), null, 'sub_completed_access');
        $this->context->set($tenant);
        $subscription->update(['status' => 'active', 'current_period_ends_at' => now()->subDay()]);
        $periodEnd = now()->addDay();
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);

        $this->sendSignedWebhook('evt_completed_access', [
            'event' => 'subscription.completed', 'created_at' => now()->timestamp,
            'payload' => ['subscription' => ['entity' => ['id' => 'sub_completed_access', 'current_end' => $periodEnd->timestamp]]],
        ])->assertNoContent();

        $this->context->set($tenant);
        $billing = app(BillingService::class);
        $this->assertSame('completed', DB::table('tenant_subscriptions')->where('id', $subscription->id)->value('status'));
        $this->assertTrue($billing->canWrite($tenant));
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('Completed (access through period end)')->assertSee('Access through');

        $this->context->set($tenant);
        $subscription->refresh()->update(['current_period_ends_at' => now()->subSecond()]);
        $this->assertFalse($billing->canWrite($tenant));
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('Completed')->assertSee('This workspace is read-only');
    }

    public function test_paused_subscription_keeps_paid_period_access_and_resumes(): void
    {
        [$tenant, $user, $subscription] = $this->workspace('Paused access', 'paused-access@example.test', now()->addDays(10), null, 'sub_paused_access');
        $this->context->set($tenant);
        $subscription->update(['status' => 'active']);
        $periodEnd = now()->addDay();
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);

        $this->sendSignedWebhook('evt_paused_access', [
            'event' => 'subscription.paused', 'created_at' => now()->subSecond()->timestamp,
            'payload' => ['subscription' => ['entity' => ['id' => 'sub_paused_access', 'current_start' => now()->subDays(10)->timestamp, 'current_end' => $periodEnd->timestamp]]],
        ])->assertNoContent();

        $this->context->set($tenant);
        $billing = app(BillingService::class);
        $this->assertSame('paused', DB::table('tenant_subscriptions')->where('id', $subscription->id)->value('status'));
        $this->assertTrue($billing->canWrite($tenant));
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('Paused (access through period end)')->assertSee('subscription is paused');

        $this->sendSignedWebhook('evt_resumed_access', [
            'event' => 'subscription.resumed', 'created_at' => now()->timestamp,
            'payload' => ['subscription' => ['entity' => ['id' => 'sub_paused_access', 'current_start' => now()->timestamp, 'current_end' => now()->addMonth()->timestamp]]],
        ])->assertNoContent();
        $this->assertSame('active', DB::table('tenant_subscriptions')->where('id', $subscription->id)->value('status'));
        $this->context->set($tenant);
        $this->assertTrue($billing->canWrite($tenant));
    }

    public function test_invalid_provider_period_timestamps_preserve_the_last_valid_access_period(): void
    {
        [$tenant, , $subscription] = $this->workspace('Malformed period event', 'malformed-period@example.test', now()->addDays(10), null, 'sub_malformed_period');
        $this->context->set($tenant);
        $periodEnd = now()->addDays(5);
        $subscription->update([
            'plan_id' => Plan::where('code', 'starter')->value('id'),
            'status' => 'active',
            'current_period_starts_at' => now()->subDays(2),
            'current_period_ends_at' => $periodEnd,
        ]);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);

        $this->sendSignedWebhook('evt_malformed_period', [
            'event' => 'subscription.paused', 'created_at' => now()->timestamp,
            'payload' => ['subscription' => ['entity' => [
                'id' => 'sub_malformed_period', 'current_start' => 'not-a-timestamp', 'current_end' => -1,
            ]]],
        ])->assertNoContent();

        $this->context->set($tenant);
        $subscription->refresh();
        $this->assertSame('paused', $subscription->status);
        $this->assertSame($periodEnd->timestamp, $subscription->current_period_ends_at->timestamp);
        $this->assertTrue(app(BillingService::class)->canWrite($tenant));
    }

    public function test_invoice_limit_redirects_to_billing_when_reached(): void
    {
        [$tenant, $user] = $this->workspace('Limited', 'limited@example.test', now()->addDays(10), 0);
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('invoices.store'), [])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('billing_limit');
    }

    public function test_monthly_invoice_limit_counts_current_month_and_blocks_at_the_exact_cap(): void
    {
        [$tenant, $owner] = $this->workspace('Monthly invoice cap', 'monthly-invoice-cap@example.test', now()->addDays(10), null);
        Plan::where('code', 'trial')->update(['monthly_invoice_limit' => 2]);
        $this->context->set($tenant);

        foreach (['CAP-ONE', 'CAP-TWO', 'CAP-OLD'] as $number) {
            Invoice::create(['number' => $number, 'invoice_date' => now()->toDateString(), 'customer_name' => 'Customer']);
        }
        DB::table('invoices')->where('tenant_id', $tenant->id)->where('number', 'CAP-OLD')->update(['created_at' => now()->subMonth()]);

        $billing = app(BillingService::class);
        $this->assertSame(2, $billing->usage($tenant)['invoices']);
        $this->assertTrue($billing->limitReached($tenant, 'invoices'));
        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('invoices.store'), [])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('billing_limit');
        $this->assertSame(3, DB::table('invoices')->where('tenant_id', $tenant->id)->count());
    }

    public function test_member_limit_allows_reinviting_an_existing_reserved_seat_but_blocks_new_invites(): void
    {
        [$tenant, $owner] = $this->workspace('Member seat cap', 'member-seat-cap@example.test', now()->addDays(10), null);
        $plan = Plan::where('code', 'trial')->firstOrFail();
        $plan->update(['member_limit' => 2]);
        $existing = TenantInvitation::create([
            'tenant_id' => $tenant->id, 'invited_by' => $owner->id, 'email' => 'existing-invite@example.test',
            'role' => 'viewer', 'token_hash' => hash('sha256', 'old-invite-token'), 'expires_at' => now()->addDays(3),
        ]);
        config(['mail.default' => 'array']);

        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('users.store'), ['email' => $existing->email, 'role' => 'staff'])
            ->assertRedirect(route('users.index'));

        $existing->refresh();
        $this->assertSame('staff', $existing->role);
        $this->assertNotSame(hash('sha256', 'old-invite-token'), $existing->token_hash);
        $this->assertTrue($existing->expires_at->isFuture());

        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('users.store'), ['email' => 'new-invite@example.test', 'role' => 'staff'])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('billing_limit');
        $this->assertDatabaseMissing('tenant_invitations', ['tenant_id' => $tenant->id, 'email' => 'new-invite@example.test']);
    }

    public function test_invitation_acceptance_rechecks_member_limit_after_plan_downgrade(): void
    {
        [$tenant, $owner] = $this->workspace('Member acceptance cap', 'member-accept-cap@example.test', now()->addDays(10), null);
        $plan = Plan::where('code', 'trial')->firstOrFail();
        $plan->update(['member_limit' => 3]);
        $plainToken = 'member-acceptance-token';
        $invite = TenantInvitation::create([
            'tenant_id' => $tenant->id, 'invited_by' => $owner->id, 'email' => 'new-member@example.test',
            'role' => 'staff', 'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDays(3),
        ]);
        $plan->update(['member_limit' => 1]);

        $this->post(route('invitations.accept.store', $plainToken), [
            'name' => 'New member', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertForbidden();

        $this->assertSame(1, $tenant->users()->count());
        $this->assertNull($invite->fresh()->accepted_at);
        $this->assertDatabaseMissing('users', ['email' => 'new-member@example.test']);
    }

    public function test_accepting_a_reserved_invitation_at_the_member_limit_uses_its_existing_seat(): void
    {
        [$tenant, $owner] = $this->workspace('Reserved member seat', 'reserved-seat@example.test', now()->addDays(10), null);
        Plan::where('code', 'trial')->update(['member_limit' => 2]);
        $plainToken = 'reserved-seat-acceptance-token';
        $invite = TenantInvitation::create([
            'tenant_id' => $tenant->id, 'invited_by' => $owner->id, 'email' => 'reserved-member@example.test',
            'role' => 'staff', 'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDays(3),
        ]);

        $this->post(route('invitations.accept.store', $plainToken), [
            'name' => 'Reserved member', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $member = User::where('email', 'reserved-member@example.test')->firstOrFail();
        $this->assertTrue($tenant->users()->whereKey($member->id)->exists());
        $this->assertSame('staff', $tenant->users()->whereKey($member->id)->first()->pivot->role);
        $this->assertNotNull($invite->fresh()->accepted_at);
        $this->assertSame(2, app(BillingService::class)->usage($tenant)['members']);
    }

    public function test_expired_invitation_does_not_reserve_a_member_seat(): void
    {
        [$tenant, $owner] = $this->workspace('Expired member seat', 'expired-seat@example.test', now()->addDays(10), null);
        Plan::where('code', 'trial')->update(['member_limit' => 2]);
        TenantInvitation::create([
            'tenant_id' => $tenant->id, 'invited_by' => $owner->id, 'email' => 'old-seat@example.test',
            'role' => 'staff', 'token_hash' => hash('sha256', 'expired-seat-token'), 'expires_at' => now()->subMinute(),
        ]);
        config(['mail.default' => 'array']);

        $this->actingAs($owner)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('users.store'), ['email' => 'fresh-seat@example.test', 'role' => 'staff'])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenant->id, 'email' => 'fresh-seat@example.test', 'accepted_at' => null,
        ]);
        $this->assertSame(2, app(BillingService::class)->usage($tenant)['members']);
    }

    public function test_razorpay_webhook_signature_and_event_idempotency(): void
    {
        [$tenant, , $subscription] = $this->workspace('Webhook', 'webhook@example.test', now()->addDays(10), null, 'sub_test_123');
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);
        $raw = json_encode([
            'event' => 'subscription.activated',
            'payload' => ['subscription' => ['entity' => ['id' => 'sub_test_123', 'current_start' => now()->timestamp, 'current_end' => now()->addMonth()->timestamp]]],
        ], JSON_THROW_ON_ERROR);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_test_123',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, 'webhook-secret'),
        ];

        $this->call('POST', route('billing.webhook'), [], [], [], $headers, $raw)->assertNoContent();
        $this->call('POST', route('billing.webhook'), [], [], [], $headers, $raw)->assertNoContent();

        $this->assertSame(1, DB::table('billing_events')->where('provider_event_id', 'evt_test_123')->count());
        $this->assertSame('active', DB::table('tenant_subscriptions')->where('id', $subscription->id)->value('status'));
        $this->assertSame($tenant->id, DB::table('billing_events')->where('provider_event_id', 'evt_test_123')->value('tenant_id'));
    }

    public function test_invalid_razorpay_webhook_signature_is_rejected(): void
    {
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);
        $raw = '{"event":"subscription.activated"}';
        $this->call('POST', route('billing.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_bad',
            'HTTP_X_RAZORPAY_SIGNATURE' => 'invalid',
        ], $raw)->assertBadRequest();
        $this->assertSame(0, DB::table('billing_events')->where('provider_event_id', 'evt_bad')->count());
    }

    public function test_webhook_rejects_missing_or_oversized_event_ids_invalid_json_and_unconfigured_secret(): void
    {
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);
        $validBody = '{"event":"unknown.event"}';
        $send = fn (string $eventId, string $body) => $this->call('POST', route('billing.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_EVENT_ID' => $eventId,
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'webhook-secret'),
        ], $body);

        $send('', $validBody)->assertBadRequest();
        $send(str_repeat('x', 256), $validBody)->assertBadRequest();
        $send('evt_invalid_json', '{')->assertBadRequest();
        $send('   ', $validBody)->assertBadRequest();
        $this->assertSame(0, DB::table('billing_events')->count());

        config(['billing.razorpay.webhook_secret' => null]);
        $this->call('POST', route('billing.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_unconfigured',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $validBody, 'webhook-secret'),
        ], $validBody)->assertStatus(503);
        $this->assertSame(0, DB::table('billing_events')->count());

        config(['billing.razorpay.webhook_secret' => '   ']);
        $this->call('POST', route('billing.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_blank_secret',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $validBody, 'webhook-secret'),
        ], $validBody)->assertStatus(503);
        $this->assertSame(0, DB::table('billing_events')->count());
    }

    public function test_webhook_for_not_yet_persisted_subscription_is_retryable(): void
    {
        [$tenant, , $subscription] = $this->workspace('Webhook race', 'webhook-race@example.test', now()->addDays(10), null);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);
        $raw = json_encode([
            'event' => 'subscription.activated',
            'payload' => ['subscription' => ['entity' => ['id' => 'sub_webhook_race', 'current_start' => now()->timestamp, 'current_end' => now()->addMonth()->timestamp]]],
        ], JSON_THROW_ON_ERROR);
        $headers = [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_webhook_race',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, 'webhook-secret'),
        ];

        $this->call('POST', route('billing.webhook'), [], [], [], $headers, $raw)->assertStatus(503);
        $this->assertSame(0, DB::table('billing_events')->where('provider_event_id', 'evt_webhook_race')->count());

        $this->context->set($tenant);
        $subscription->update(['razorpay_subscription_id' => 'sub_webhook_race']);
        $this->call('POST', route('billing.webhook'), [], [], [], $headers, $raw)->assertNoContent();
        $this->assertSame(1, DB::table('billing_events')->where('provider_event_id', 'evt_webhook_race')->count());
        $this->assertSame('active', DB::table('tenant_subscriptions')->where('id', $subscription->id)->value('status'));
    }

    public function test_halted_initial_checkout_is_released_without_replacing_current_trial(): void
    {
        [$tenant, $user, $trial] = $this->workspace('Halted initial checkout', 'halted-initial@example.test', now()->addDays(10), null);
        $this->context->set($tenant);
        $pending = TenantSubscription::create([
            'plan_id' => Plan::where('code', 'starter')->value('id'),
            'status' => 'pending',
            'billing_interval' => 'monthly',
            'razorpay_subscription_id' => 'sub_halted_initial',
        ]);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);

        $this->sendSignedWebhook('evt_halted_initial', [
            'event' => 'subscription.halted',
            'created_at' => now()->timestamp,
            'payload' => ['subscription' => ['entity' => ['id' => 'sub_halted_initial']]],
        ])->assertNoContent();

        $this->context->set($tenant);
        $billing = app(BillingService::class);
        $this->assertSame('checkout_failed', $pending->fresh()->status);
        $this->assertSame($trial->id, $billing->subscription($tenant)->id);
        $this->assertTrue($billing->canWrite($tenant));
        $this->assertTrue($billing->canStartCheckout($tenant));

        $this->sendSignedWebhook('evt_late_initial_failure', [
            'event' => 'payment.failed',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_halted_initial']],
                'payment' => ['entity' => [
                    'id' => 'pay_halted_initial', 'subscription_id' => 'sub_halted_initial',
                    'amount' => 150000, 'currency' => 'INR',
                ]],
            ],
        ])->assertNoContent();
        $this->context->set($tenant);
        $pending->refresh();
        $this->assertSame('checkout_failed', $pending->status);
        $this->assertSame($trial->id, $billing->subscription($tenant)->id);
        $this->assertSame('failed', DB::table('billing_payments')->where('provider_payment_id', 'pay_halted_initial')->value('status'));

        $this->sendSignedWebhook('evt_late_initial_pending', [
            'event' => 'subscription.pending',
            'created_at' => now()->addMinute()->timestamp,
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_halted_initial']],
                'payment' => ['entity' => [
                    'id' => 'pay_halted_initial_pending',
                    'subscription_id' => 'sub_halted_initial',
                    'amount' => 150000,
                    'currency' => 'INR',
                ]],
            ],
        ])->assertNoContent();
        $this->context->set($tenant);
        $pending->refresh();
        $this->assertSame('checkout_failed', $pending->status);
        $this->assertSame($trial->id, $billing->subscription($tenant)->id);
        $this->assertSame('failed', DB::table('billing_payments')->where('provider_payment_id', 'pay_halted_initial_pending')->value('status'));

        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('provider halted the pending Starter checkout')->assertSee('Your current plan access is unchanged');

        $this->context->set($tenant);
        TenantSubscription::create([
            'plan_id' => Plan::where('code', 'starter')->value('id'),
            'status' => 'pending',
            'billing_interval' => 'monthly',
            'razorpay_subscription_id' => 'sub_halted_initial_retry',
        ]);
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('Pending checkout')->assertDontSee('provider halted the pending Starter checkout');
    }

    public function test_checkout_requires_configured_terms_and_creates_provider_subscription(): void
    {
        [$tenant, $user, $trial] = $this->workspace('Checkout', 'checkout@example.test', now()->addDays(10), null);
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update(['is_active' => true, 'monthly_price_paise' => 150000, 'razorpay_plan_monthly_id' => 'plan_test_monthly', 'gst_reports' => true, 'custom_branding' => false, 'api_access' => false]);
        config([
            'billing.razorpay.key' => 'key_test', 'billing.razorpay.secret' => 'secret_test',
            'billing.razorpay.webhook_secret' => 'webhook_test',
            'billing.subscription_cycle_count.monthly' => 12,
            'billing.seller' => ['name' => 'SaaS Seller', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A', 'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'inclusive'],
        ]);
        Http::fake([
            'https://api.razorpay.com/v1/plans/plan_test_monthly' => Http::response(['period' => 'monthly', 'item' => ['amount' => 150000, 'currency' => 'INR']], 200),
            'https://api.razorpay.com/v1/subscriptions' => Http::response(['id' => 'sub_checkout_1', 'short_url' => 'https://rzp.io/i/checkout-1'], 200),
        ]);

        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('billing.checkout', $plan), ['interval' => 'monthly'])
            ->assertRedirect('https://rzp.io/i/checkout-1');

        $pending = DB::table('tenant_subscriptions')->where('razorpay_subscription_id', 'sub_checkout_1')->first();
        $this->assertNotNull($pending);
        $this->assertSame('pending', $pending->status);
        $this->assertSame('trialing', DB::table('tenant_subscriptions')->where('id', $trial->id)->value('status'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/subscriptions') && $request['total_count'] === 12 && $request['quantity'] === 1 && $request['plan_id'] === 'plan_test_monthly');
    }

    public function test_checkout_stays_disabled_when_product_tax_terms_are_unset(): void
    {
        [$tenant, $user] = $this->workspace('Unconfigured', 'unconfigured@example.test', now()->addDays(10), null);
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update(['is_active' => true, 'monthly_price_paise' => 150000, 'razorpay_plan_monthly_id' => 'plan_test_monthly']);
        config([
            'billing.razorpay.key' => 'key_test', 'billing.razorpay.secret' => 'secret_test',
            'billing.subscription_cycle_count.monthly' => 12,
        ]);

        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('billing.checkout', $plan), ['interval' => 'monthly'])
            ->assertStatus(422);
    }

    public function test_checkout_rejects_whitespace_only_seller_tax_details(): void
    {
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update([
            'is_active' => true,
            'monthly_price_paise' => 150000,
            'razorpay_plan_monthly_id' => 'plan_seller_details',
            'gst_reports' => true,
            'custom_branding' => false,
            'api_access' => false,
        ]);
        config([
            'billing.razorpay.key' => 'key_test',
            'billing.razorpay.secret' => 'secret_test',
            'billing.razorpay.webhook_secret' => 'webhook_test',
            'billing.subscription_cycle_count.monthly' => 12,
            'billing.seller' => [
                'name' => 'SaaS Seller', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A',
                'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18,
                'sac_code' => '998313', 'price_tax_mode' => 'inclusive',
            ],
        ]);
        $billing = app(BillingService::class);
        $this->assertTrue($billing->checkoutReady($plan, 'monthly'));

        foreach (['name', 'gstin', 'pan', 'address', 'state_code', 'sac_code', 'price_tax_mode'] as $field) {
            config(["billing.seller.{$field}" => '   ']);
            $this->assertFalse($billing->checkoutReady($plan, 'monthly'), "Whitespace-only seller {$field} must disable checkout.");
            config(["billing.seller.{$field}" => match ($field) {
                'name' => 'SaaS Seller',
                'gstin' => '27AAAAA0000A1Z5',
                'pan' => 'AAAAA0000A',
                'address' => 'Seller Street',
                'state_code' => '27',
                'sac_code' => '998313',
                'price_tax_mode' => 'inclusive',
            }]);
        }

        config(['billing.seller.gst_rate' => 0]);
        $this->assertFalse($billing->checkoutReady($plan, 'monthly'));
    }

    public function test_checkout_requires_webhook_secret_before_accepting_payment(): void
    {
        [$tenant, $user] = $this->workspace('Webhook config required', 'webhook-config@example.test', now()->addDays(10), null);
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update([
            'is_active' => true, 'monthly_price_paise' => 150000, 'razorpay_plan_monthly_id' => 'plan_webhook_config',
            'gst_reports' => true, 'custom_branding' => false, 'api_access' => false,
        ]);
        config([
            'billing.razorpay.key' => 'key_test', 'billing.razorpay.secret' => 'secret_test',
            'billing.razorpay.webhook_secret' => null,
            'billing.subscription_cycle_count.monthly' => 12,
            'billing.seller' => ['name' => 'SaaS Seller', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A', 'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'inclusive'],
        ]);
        Http::fake();

        $billing = app(BillingService::class);
        $this->assertFalse($billing->checkoutReady($plan, 'monthly'));
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('billing.checkout', $plan), ['interval' => 'monthly'])
            ->assertStatus(422);
        Http::assertNothingSent();

        config(['billing.razorpay.webhook_secret' => 'webhook_test']);
        $this->assertTrue($billing->checkoutReady($plan, 'monthly'));
    }

    public function test_yearly_exclusive_checkout_uses_yearly_provider_plan_and_gross_price(): void
    {
        [$tenant, $user] = $this->workspace('Yearly checkout', 'yearly-checkout@example.test', now()->addDays(10), null);
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update([
            'is_active' => true, 'yearly_price_paise' => 150000,
            'razorpay_plan_yearly_id' => 'plan_yearly_exclusive',
            'gst_reports' => true, 'custom_branding' => false, 'api_access' => false,
        ]);
        config([
            'billing.razorpay.key' => 'key_test', 'billing.razorpay.secret' => 'secret_test',
            'billing.razorpay.webhook_secret' => 'webhook_test',
            'billing.subscription_cycle_count.yearly' => 3,
            'billing.seller' => ['name' => 'SaaS Seller', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A', 'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'exclusive'],
        ]);
        Http::fake([
            'https://api.razorpay.com/v1/plans/plan_yearly_exclusive' => Http::response(['period' => 'yearly', 'item' => ['amount' => 177000, 'currency' => 'INR']], 200),
            'https://api.razorpay.com/v1/subscriptions' => Http::response(['id' => 'sub_yearly_checkout', 'short_url' => 'https://rzp.io/i/yearly-checkout'], 200),
        ]);

        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('billing.checkout', $plan), ['interval' => 'yearly'])
            ->assertRedirect('https://rzp.io/i/yearly-checkout');

        $pending = DB::table('tenant_subscriptions')->where('razorpay_subscription_id', 'sub_yearly_checkout')->first();
        $this->assertNotNull($pending);
        $this->assertSame('yearly', $pending->billing_interval);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/subscriptions')
            && $request['plan_id'] === 'plan_yearly_exclusive' && $request['total_count'] === 3);
    }

    public function test_subscription_cycle_count_limits_accept_the_documented_maximum_and_reject_overflow(): void
    {
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update([
            'is_active' => true, 'monthly_price_paise' => 10000, 'yearly_price_paise' => 100000,
            'razorpay_plan_monthly_id' => 'plan_cycle_monthly', 'razorpay_plan_yearly_id' => 'plan_cycle_yearly',
            'gst_reports' => true, 'custom_branding' => false, 'api_access' => false,
        ]);
        config([
            'billing.razorpay.key' => 'key_test', 'billing.razorpay.secret' => 'secret_test',
            'billing.razorpay.webhook_secret' => 'webhook_test',
            'billing.seller' => ['name' => 'SaaS Seller', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A', 'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'inclusive'],
            'billing.subscription_cycle_count.monthly' => 1200,
            'billing.subscription_cycle_count.yearly' => 100,
        ]);
        $billing = app(BillingService::class);
        $this->assertTrue($billing->checkoutReady($plan, 'monthly'));
        $this->assertTrue($billing->checkoutReady($plan, 'yearly'));

        config(['billing.subscription_cycle_count.monthly' => 1201, 'billing.subscription_cycle_count.yearly' => 101]);
        $this->assertFalse($billing->checkoutReady($plan, 'monthly'));
        $this->assertFalse($billing->checkoutReady($plan, 'yearly'));
        config(['billing.subscription_cycle_count.monthly' => 0, 'billing.subscription_cycle_count.yearly' => -1]);
        $this->assertFalse($billing->checkoutReady($plan, 'monthly'));
        $this->assertFalse($billing->checkoutReady($plan, 'yearly'));
    }

    public function test_free_trial_legacy_and_zero_priced_plans_are_not_checkout_ready(): void
    {
        $starter = Plan::where('code', 'starter')->firstOrFail();
        $starter->update([
            'is_active' => true, 'monthly_price_paise' => 0, 'yearly_price_paise' => 100000,
            'razorpay_plan_monthly_id' => 'plan_zero_monthly', 'razorpay_plan_yearly_id' => 'plan_starter_yearly',
            'gst_reports' => true, 'custom_branding' => false, 'api_access' => false,
        ]);
        $trial = Plan::where('code', 'trial')->firstOrFail();
        $trial->update([
            'is_active' => true, 'monthly_price_paise' => 10000, 'yearly_price_paise' => 100000,
            'razorpay_plan_monthly_id' => 'plan_trial_monthly', 'razorpay_plan_yearly_id' => 'plan_trial_yearly',
            'gst_reports' => true, 'custom_branding' => false, 'api_access' => false,
        ]);
        $legacy = Plan::where('code', 'legacy')->firstOrFail();
        $legacy->update([
            'is_active' => true, 'monthly_price_paise' => 10000, 'yearly_price_paise' => 100000,
            'razorpay_plan_monthly_id' => 'plan_legacy_monthly', 'razorpay_plan_yearly_id' => 'plan_legacy_yearly',
            'gst_reports' => true, 'custom_branding' => false, 'api_access' => false,
        ]);
        config([
            'billing.razorpay.key' => 'key_test', 'billing.razorpay.secret' => 'secret_test',
            'billing.razorpay.webhook_secret' => 'webhook_test',
            'billing.subscription_cycle_count.monthly' => 12, 'billing.subscription_cycle_count.yearly' => 2,
            'billing.seller' => ['name' => 'SaaS Seller', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A', 'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'inclusive'],
        ]);
        $billing = app(BillingService::class);

        $this->assertFalse($billing->checkoutReady($starter, 'monthly'));
        $this->assertTrue($billing->checkoutReady($starter, 'yearly'));
        $this->assertFalse($billing->checkoutReady($trial, 'monthly'));
        $this->assertFalse($billing->checkoutReady($trial, 'yearly'));
        $this->assertFalse($billing->checkoutReady($legacy, 'monthly'));
        $this->assertFalse($billing->checkoutReady($legacy, 'yearly'));
    }

    public function test_invalid_provider_checkout_response_does_not_create_a_stuck_pending_subscription(): void
    {
        [$tenant, $user] = $this->workspace('Broken checkout response', 'broken-checkout@example.test', now()->addDays(10), null);
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update([
            'is_active' => true, 'monthly_price_paise' => 150000, 'razorpay_plan_monthly_id' => 'plan_broken_checkout',
            'gst_reports' => true, 'custom_branding' => false, 'api_access' => false,
        ]);
        config([
            'billing.razorpay.key' => 'key_test', 'billing.razorpay.secret' => 'secret_test',
            'billing.razorpay.webhook_secret' => 'webhook_test',
            'billing.subscription_cycle_count.monthly' => 12,
            'billing.seller' => ['name' => 'SaaS Seller', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A', 'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'inclusive'],
        ]);
        Http::fake([
            'https://api.razorpay.com/v1/plans/plan_broken_checkout' => Http::response(['period' => 'monthly', 'item' => ['amount' => 150000, 'currency' => 'INR']], 200),
            'https://api.razorpay.com/v1/subscriptions' => Http::response(['id' => 'sub_without_checkout_url'], 200),
        ]);

        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('billing.checkout', $plan), ['interval' => 'monthly'])
            ->assertStatus(502);
        $this->assertSame(0, DB::table('tenant_subscriptions')->where('tenant_id', $tenant->id)->where('status', 'pending')->count());
    }

    public function test_concurrent_checkout_attempt_reuses_existing_pending_subscription(): void
    {
        [$tenant] = $this->workspace('Single checkout', 'single-checkout@example.test', now()->addDays(10), null);
        $this->context->set($tenant);
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update([
            'is_active' => true, 'monthly_price_paise' => 150000, 'razorpay_plan_monthly_id' => 'plan_single_checkout',
            'gst_reports' => true, 'custom_branding' => false, 'api_access' => false,
        ]);
        config([
            'billing.razorpay.key' => 'key_test', 'billing.razorpay.secret' => 'secret_test',
            'billing.razorpay.webhook_secret' => 'webhook_test',
            'billing.subscription_cycle_count.monthly' => 12,
            'billing.seller' => ['name' => 'Demo Seller', 'gstin' => 'DEMO', 'pan' => 'DEMO', 'address' => 'Demo', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => 'DEMO', 'price_tax_mode' => 'inclusive'],
        ]);
        $pending = TenantSubscription::create([
            'plan_id' => $plan->id, 'status' => 'pending', 'billing_interval' => 'monthly',
            'razorpay_subscription_id' => 'sub_single_checkout',
            'provider_metadata' => ['short_url' => 'https://rzp.io/i/single-checkout'],
        ]);
        Http::fake();

        $result = app(BillingService::class)->createCheckout($tenant, $plan, 'monthly');

        $this->assertSame($pending->id, $result->id);
        $this->assertSame(1, DB::table('tenant_subscriptions')->where('tenant_id', $tenant->id)->where('status', 'pending')->count());
        Http::assertNothingSent();
    }

    public function test_pending_checkout_recovers_missing_url_from_existing_provider_subscription(): void
    {
        [$tenant, $user] = $this->workspace('Recover checkout', 'recover-checkout@example.test', now()->addDays(10), null);
        $this->context->set($tenant);
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update([
            'is_active' => true, 'monthly_price_paise' => 150000, 'razorpay_plan_monthly_id' => 'plan_recover_checkout',
            'gst_reports' => true, 'custom_branding' => false, 'api_access' => false,
        ]);
        config([
            'billing.razorpay.key' => 'key_test', 'billing.razorpay.secret' => 'secret_test',
            'billing.razorpay.webhook_secret' => 'webhook_test',
            'billing.subscription_cycle_count.monthly' => 12,
            'billing.seller' => ['name' => 'SaaS Seller', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A', 'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'inclusive'],
        ]);
        $pending = TenantSubscription::create([
            'plan_id' => $plan->id, 'status' => 'pending', 'billing_interval' => 'monthly',
            'razorpay_subscription_id' => 'sub_recover_checkout', 'provider_metadata' => [],
        ]);
        Http::fake([
            'https://api.razorpay.com/v1/subscriptions/sub_recover_checkout' => Http::response([
                'id' => 'sub_recover_checkout', 'short_url' => 'https://rzp.io/i/recovered-checkout',
            ], 200),
        ]);

        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('Restore checkout link');
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('billing.checkout', $plan), ['interval' => 'monthly'])
            ->assertRedirect('https://rzp.io/i/recovered-checkout');

        $this->context->set($tenant);
        $this->assertSame('https://rzp.io/i/recovered-checkout', TenantSubscription::whereKey($pending->id)->firstOrFail()->provider_metadata['short_url']);
        $this->assertSame(1, DB::table('tenant_subscriptions')->where('tenant_id', $tenant->id)->where('status', 'pending')->count());
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/subscriptions/sub_recover_checkout'));
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/subscriptions'));
    }

    public function test_existing_pending_checkout_link_is_blocked_without_webhook_secret(): void
    {
        [$tenant, $user] = $this->workspace('Webhook unavailable', 'webhook-unavailable@example.test', now()->addDays(10), null);
        $this->context->set($tenant);
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $pending = TenantSubscription::create([
            'plan_id' => $plan->id,
            'status' => 'pending',
            'billing_interval' => 'monthly',
            'razorpay_subscription_id' => 'sub_webhook_unavailable',
            'provider_metadata' => ['short_url' => 'https://rzp.io/i/pending-without-webhook'],
        ]);
        foreach ([null, '   '] as $secret) {
            config(['billing.razorpay.webhook_secret' => $secret]);

            $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
                ->get(route('billing.index'))
                ->assertOk()
                ->assertSee('webhook authentication is not configured')
                ->assertDontSee('https://rzp.io/i/pending-without-webhook');

            $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
                ->post(route('billing.checkout', $plan), ['interval' => 'monthly'])
                ->assertRedirect(route('billing.index'))
                ->assertSessionHas('billing_unavailable', 'Checkout is temporarily unavailable until webhook authentication is configured.');
        }

        $this->context->set($tenant);
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_pending_checkout_requires_a_nonblank_workspace_state_code(): void
    {
        [$tenant, $user] = $this->workspace('Pending state code', 'pending-state-code@example.test', now()->addDays(10), null);
        $this->context->set($tenant);
        $pending = TenantSubscription::create([
            'plan_id' => Plan::where('code', 'starter')->value('id'),
            'status' => 'pending',
            'billing_interval' => 'monthly',
            'razorpay_subscription_id' => 'sub_pending_state_code',
            'provider_metadata' => ['short_url' => 'https://rzp.io/i/pending-state-code'],
        ]);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);

        foreach ([null, '   '] as $stateCode) {
            $tenant->update(['state_code' => $stateCode]);

            $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
                ->get(route('billing.index'))
                ->assertOk()
                ->assertSee('Complete the workspace state code before continuing this checkout')
                ->assertDontSee('https://rzp.io/i/pending-state-code');

            $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
                ->post(route('billing.checkout', $pending->plan), ['interval' => 'monthly'])
                ->assertUnprocessable()
                ->assertSeeText('Complete the workspace state code before starting a paid subscription.');
        }

        $this->context->set($tenant);
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_billing_invoice_download_is_scoped_to_the_active_tenant(): void
    {
        [$tenantA, $userA] = $this->workspace('Billing A', 'billing-a@example.test', now()->addDays(10), null);
        [$tenantB, $userB] = $this->workspace('Billing B', 'billing-b@example.test', now()->addDays(10), null);
        $this->context->set($tenantA);
        $invoice = BillingInvoice::create([
            'number' => 'SaaS-test-a', 'subtotal_paise' => 10000, 'tax_paise' => 1800, 'total_paise' => 11800,
            'currency' => 'INR', 'tax_breakdown' => ['IGST' => 1800],
            'seller_snapshot' => ['name' => 'Seller', 'gstin' => 'GSTIN', 'pan' => 'PAN', 'address' => 'Address', 'sac_code' => '9983'],
            'buyer_snapshot' => ['name' => 'Billing A'], 'issued_at' => now(),
        ]);
        $this->actingAs($userA)->withSession(['current_tenant_id' => $tenantA->id])->get(route('billing.invoices.download', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($userB)->withSession(['current_tenant_id' => $tenantB->id])->get(route('billing.invoices.download', $invoice->id))->assertNotFound();
    }

    public function test_billing_page_checkout_and_invoice_download_are_owner_only(): void
    {
        [$tenant] = $this->workspace('Owner billing', 'owner-billing@example.test', now()->addDays(10), null);
        $this->context->set($tenant);
        $invoice = BillingInvoice::create([
            'number' => 'SaaS-owner-only', 'subtotal_paise' => 10000, 'tax_paise' => 1800, 'total_paise' => 11800,
            'currency' => 'INR', 'tax_breakdown' => ['IGST' => 1800],
            'seller_snapshot' => ['name' => 'Seller'], 'buyer_snapshot' => ['name' => $tenant->name], 'issued_at' => now(),
        ]);
        $admin = User::create(['name' => 'Workspace Admin', 'email' => 'billing-admin@example.test', 'password' => 'password', 'role' => 'admin', 'email_verified_at' => now()]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $tenant->users()->attach($admin->id, ['role' => 'admin']);
        $plan = Plan::where('code', 'starter')->firstOrFail();

        $this->actingAs($admin)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))->assertForbidden();
        $this->actingAs($admin)->withSession(['current_tenant_id' => $tenant->id])->post(route('billing.checkout', $plan), ['interval' => 'monthly'])->assertForbidden();
        $this->actingAs($admin)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.invoices.download', $invoice))->assertForbidden();
    }

    public function test_billing_records_reject_cross_tenant_links_and_tenant_id_tampering(): void
    {
        [$tenantA] = $this->workspace('Billing relationship A', 'billing-relation-a@example.test', now()->addDays(10), null);
        [$tenantB, , $subscriptionB] = $this->workspace('Billing relationship B', 'billing-relation-b@example.test', now()->addDays(10), null);
        $this->context->set($tenantB);
        $paymentB = BillingPayment::create([
            'subscription_id' => $subscriptionB->id, 'provider_payment_id' => 'pay_relation_b',
            'amount_paise' => 11800, 'currency' => 'INR', 'status' => 'captured', 'paid_at' => now(),
        ]);

        $this->context->set($tenantA);
        $invalidPaymentRejected = false;
        try {
            BillingPayment::create([
                'tenant_id' => $tenantB->id, 'provider_payment_id' => 'pay_tampered_tenant',
                'amount_paise' => 10000, 'currency' => 'INR', 'status' => 'captured',
            ]);
        } catch (ValidationException) {
            $invalidPaymentRejected = true;
        }
        $this->assertTrue($invalidPaymentRejected);

        $crossTenantSubscriptionRejected = false;
        try {
            BillingPayment::create([
                'subscription_id' => $subscriptionB->id, 'provider_payment_id' => 'pay_cross_subscription',
                'amount_paise' => 10000, 'currency' => 'INR', 'status' => 'captured',
            ]);
        } catch (ValidationException) {
            $crossTenantSubscriptionRejected = true;
        }
        $this->assertTrue($crossTenantSubscriptionRejected);

        $crossTenantPaymentRejected = false;
        try {
            BillingInvoice::create([
                'payment_id' => $paymentB->id, 'number' => 'SaaS-cross-tenant',
                'subtotal_paise' => 10000, 'tax_paise' => 1800, 'total_paise' => 11800,
                'currency' => 'INR', 'seller_snapshot' => ['name' => 'Seller'],
                'buyer_snapshot' => ['name' => 'Billing relationship A'], 'issued_at' => now(),
            ]);
        } catch (ValidationException) {
            $crossTenantPaymentRejected = true;
        }
        $this->assertTrue($crossTenantPaymentRejected);

        $tamperedSubscriptionRejected = false;
        $this->assertSame((int) $tenantB->id, (int) $subscriptionB->tenant_id);
        $subscriptionB->tenant_id = $tenantA->id;
        try {
            $subscriptionB->save();
        } catch (ValidationException) {
            $tamperedSubscriptionRejected = true;
        }
        $this->assertTrue($tamperedSubscriptionRejected);
        $this->assertSame(0, DB::table('billing_invoices')->where('tenant_id', $tenantA->id)->count());
    }

    public function test_successful_subscription_charge_creates_idempotent_payment_and_configured_tax_invoice(): void
    {
        [$tenant, , $subscription] = $this->workspace('Paid', 'paid@example.test', now()->addDays(10), null, 'sub_paid_123');
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update(['monthly_price_paise' => 11800]);
        $this->context->set($tenant);
        $subscription->update(['plan_id' => $plan->id, 'billing_interval' => 'monthly', 'status' => 'pending']);
        $tenant->update(['gstin' => '27ABCDE1234F1Z5', 'state_code' => ' 27 ', 'address' => 'Buyer Street']);
        config(['billing.seller' => [
            'name' => 'InvoiceIQ Services Pvt Ltd', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A',
            'address' => 'Seller Street', 'state_code' => ' 27 ', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'inclusive',
        ]]);
        $raw = json_encode([
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_paid_123']],
                'payment' => ['entity' => ['id' => 'pay_paid_123', 'subscription_id' => 'sub_paid_123', 'amount' => 11800, 'currency' => 'INR', 'method' => 'card', 'created_at' => now()->timestamp]],
            ],
        ], JSON_THROW_ON_ERROR);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_paid_123', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, 'webhook-secret')];

        $this->call('POST', route('billing.webhook'), [], [], [], $headers, $raw)->assertNoContent();
        $this->call('POST', route('billing.webhook'), [], [], [], $headers, $raw)->assertNoContent();
        $this->sendSignedWebhook('evt_paid_duplicate_payment', [
            'event' => 'payment.captured',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_paid_123']],
                'payment' => ['entity' => [
                    'id' => 'pay_paid_123',
                    'subscription_id' => 'sub_paid_123',
                    'amount' => 99999,
                    'currency' => 'USD',
                    'method' => 'netbanking',
                    'created_at' => now()->timestamp,
                ]],
            ],
        ])->assertNoContent();

        $this->assertSame(1, DB::table('billing_payments')->where('provider_payment_id', 'pay_paid_123')->count());
        $payment = DB::table('billing_payments')->where('provider_payment_id', 'pay_paid_123')->first();
        $this->assertSame('captured', $payment->status);
        $this->assertSame(11800, (int) $payment->amount_paise);
        $this->assertSame('INR', $payment->currency);
        $this->assertSame('card', $payment->method);
        $invoice = DB::table('billing_invoices')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(11800, (int) $invoice->total_paise);
        $this->assertSame(10000, (int) $invoice->subtotal_paise);
        $this->assertSame(['CGST' => 900, 'SGST' => 900], json_decode($invoice->tax_breakdown, true));
        $this->assertSame('27', json_decode($invoice->seller_snapshot, true)['state_code']);
        $this->assertSame('27', json_decode($invoice->buyer_snapshot, true)['state_code']);

        $this->sendSignedWebhook('evt_paid_late_failure', [
            'event' => 'payment.failed',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_paid_123']],
                'payment' => ['entity' => ['id' => 'pay_paid_123', 'subscription_id' => 'sub_paid_123', 'amount' => 11800, 'currency' => 'INR']],
            ],
        ])->assertNoContent();

        $this->assertSame('captured', DB::table('billing_payments')->where('provider_payment_id', 'pay_paid_123')->value('status'));
        $this->assertSame('active', DB::table('tenant_subscriptions')->where('id', $subscription->id)->value('status'));
        $this->assertSame(1, DB::table('billing_invoices')->where('payment_id', DB::table('billing_payments')->where('provider_payment_id', 'pay_paid_123')->value('id'))->count());
    }

    public function test_exclusive_subscription_price_creates_interstate_igst_invoice(): void
    {
        [$tenant, , $subscription] = $this->workspace('Interstate billing', 'interstate-billing@example.test', now()->addDays(10), null, 'sub_interstate_billing');
        $tenant->update(['state_code' => '29', 'address' => 'Buyer State']);
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update(['monthly_price_paise' => 10000]);
        $this->context->set($tenant);
        $subscription->update(['plan_id' => $plan->id, 'billing_interval' => 'monthly', 'status' => 'active']);
        config(['billing.seller' => [
            'name' => 'InvoiceIQ Services Pvt Ltd', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A',
            'address' => 'Seller State', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'exclusive',
        ]]);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);

        $this->sendSignedWebhook('evt_interstate_billing', [
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_interstate_billing']],
                'payment' => ['entity' => ['id' => 'pay_interstate_billing', 'subscription_id' => 'sub_interstate_billing', 'amount' => 11800, 'currency' => 'INR', 'created_at' => now()->timestamp]],
            ],
        ])->assertNoContent();

        $invoice = DB::table('billing_invoices')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(10000, (int) $invoice->subtotal_paise);
        $this->assertSame(1800, (int) $invoice->tax_paise);
        $this->assertSame(11800, (int) $invoice->total_paise);
        $this->assertSame(['IGST' => 1800], json_decode($invoice->tax_breakdown, true));
    }

    public function test_exclusive_same_state_gst_rounding_assigns_remainder_without_losing_a_paise(): void
    {
        [$tenant, , $subscription] = $this->workspace('Odd GST split', 'odd-gst-split@example.test', now()->addDays(10), null, 'sub_odd_gst_split');
        $tenant->update(['state_code' => '27', 'address' => 'Buyer Street']);
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update(['monthly_price_paise' => 10003]);
        $this->context->set($tenant);
        $subscription->update(['plan_id' => $plan->id, 'billing_interval' => 'monthly', 'status' => 'pending']);
        config([
            'billing.seller' => ['name' => 'SaaS Seller', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A', 'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'exclusive'],
            'billing.razorpay.webhook_secret' => 'webhook-secret',
        ]);

        $this->sendSignedWebhook('evt_odd_gst_split', [
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_odd_gst_split']],
                'payment' => ['entity' => ['id' => 'pay_odd_gst_split', 'subscription_id' => 'sub_odd_gst_split', 'amount' => 11804, 'currency' => 'INR']],
            ],
        ])->assertNoContent();

        $invoice = DB::table('billing_invoices')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(1801, (int) $invoice->tax_paise);
        $this->assertSame(['CGST' => 900, 'SGST' => 901], json_decode($invoice->tax_breakdown, true));
        $this->assertSame((int) $invoice->tax_paise, array_sum(json_decode($invoice->tax_breakdown, true)));
    }

    public function test_tax_invoice_is_not_issued_when_seller_tax_details_are_blank(): void
    {
        [$tenant, , $subscription] = $this->workspace('Blank seller details', 'blank-seller@example.test', now()->addDays(10), null, 'sub_blank_seller');
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update(['monthly_price_paise' => 10000]);
        $this->context->set($tenant);
        $subscription->update(['plan_id' => $plan->id, 'billing_interval' => 'monthly', 'status' => 'pending']);
        config([
            'billing.razorpay.webhook_secret' => 'webhook-secret',
            'billing.seller' => [
                'name' => 'SaaS Seller', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A',
                'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18,
                'sac_code' => '998313', 'price_tax_mode' => 'inclusive',
            ],
        ]);

        foreach (['name', 'gstin', 'pan', 'address', 'state_code', 'sac_code', 'price_tax_mode'] as $field) {
            config(["billing.seller.{$field}" => '   ']);
            $this->sendSignedWebhook('evt_blank_seller_'.$field, [
                'event' => 'payment.captured',
                'payload' => ['payment' => ['entity' => [
                    'id' => 'pay_blank_seller_'.$field,
                    'subscription_id' => 'sub_blank_seller',
                    'amount' => 10000,
                    'currency' => 'INR',
                ]]],
            ])->assertNoContent();
            config(["billing.seller.{$field}" => match ($field) {
                'name' => 'SaaS Seller',
                'gstin' => '27AAAAA0000A1Z5',
                'pan' => 'AAAAA0000A',
                'address' => 'Seller Street',
                'state_code' => '27',
                'sac_code' => '998313',
                'price_tax_mode' => 'inclusive',
            }]);
        }

        config(['billing.seller.gst_rate' => 0]);
        $this->sendSignedWebhook('evt_blank_seller_rate', [
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_blank_seller_rate',
                'subscription_id' => 'sub_blank_seller',
                'amount' => 10000,
                'currency' => 'INR',
            ]]],
        ])->assertNoContent();

        $this->assertSame(8, DB::table('billing_payments')->where('tenant_id', $tenant->id)->where('status', 'captured')->count());
        $this->assertSame(0, DB::table('billing_invoices')->where('tenant_id', $tenant->id)->count());
    }

    public function test_non_inr_zero_and_mismatched_captures_are_recorded_without_tax_invoices(): void
    {
        [$tenant] = $this->workspace('Invalid capture invoice', 'invalid-capture-invoice@example.test', now()->addDays(10), null, 'sub_invalid_capture_invoice');
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update(['monthly_price_paise' => 10000]);
        $this->context->set($tenant);
        $subscription = TenantSubscription::where('razorpay_subscription_id', 'sub_invalid_capture_invoice')->firstOrFail();
        $subscription->update(['plan_id' => $plan->id, 'billing_interval' => 'monthly', 'status' => 'pending']);
        config(['billing.seller' => [
            'name' => 'SaaS Seller', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A',
            'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'inclusive',
        ], 'billing.razorpay.webhook_secret' => 'webhook-secret']);

        foreach ([
            ['pay_foreign', 11800, 'USD'], ['pay_zero', 0, 'INR'], ['pay_mismatched', 9999, 'INR'],
            ['pay_missing_currency', 10000, null], ['pay_decimal_amount', '11800.5', 'INR'],
            ['pay_negative_amount', -1, 'INR'], ['pay_overflow_amount', '9999999999999999999999999', 'INR'],
        ] as [$paymentId, $amount, $currency]) {
            $this->sendSignedWebhook('evt_'.$paymentId, [
                'event' => 'payment.captured',
                'payload' => ['payment' => ['entity' => [
                    'id' => $paymentId, 'subscription_id' => 'sub_invalid_capture_invoice',
                    'amount' => $amount, 'currency' => $currency,
                    'created_at' => $paymentId === 'pay_missing_currency' ? 'not-a-timestamp' : now()->timestamp,
                ]]],
            ])->assertNoContent();
        }

        config(['billing.seller.price_tax_mode' => 'exclusive']);
        $this->sendSignedWebhook('evt_pay_exclusive_mismatch', [
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_exclusive_mismatch', 'subscription_id' => 'sub_invalid_capture_invoice',
                'amount' => 11799, 'currency' => 'INR',
            ]]],
        ])->assertNoContent();

        $this->assertSame(5, DB::table('billing_payments')->where('tenant_id', $tenant->id)->where('status', 'captured')->count());
        $this->assertSame('XXX', DB::table('billing_payments')->where('provider_payment_id', 'pay_missing_currency')->value('currency'));
        $this->assertSame(now()->year, now()->parse(DB::table('billing_payments')->where('provider_payment_id', 'pay_missing_currency')->value('paid_at'))->year);
        $this->assertSame(0, DB::table('billing_payments')->whereIn('provider_payment_id', ['pay_decimal_amount', 'pay_negative_amount', 'pay_overflow_amount'])->count());
        $this->assertSame(0, DB::table('billing_invoices')->where('tenant_id', $tenant->id)->count());
    }

    public function test_failed_renewal_is_saved_once_without_issuing_an_invoice(): void
    {
        [$tenant, , $subscription] = $this->workspace('Failed renewal', 'failed-renewal@example.test', now()->addDays(10), null, 'sub_failed_123');
        $this->context->set($tenant);
        $subscription->update(['status' => 'active']);
        config(['billing.grace_days' => 2]);
        $raw = json_encode([
            'event' => 'payment.failed',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_failed_123']],
                'payment' => ['entity' => [
                    'id' => 'pay_failed_123', 'subscription_id' => 'sub_failed_123', 'amount' => 11800,
                    'currency' => 'INR', 'method' => 'card', 'order_id' => 'order_failed_123', 'error_code' => 'BAD_REQUEST_ERROR',
                ]],
            ],
        ], JSON_THROW_ON_ERROR);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);
        $headers = [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_failed_123',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, 'webhook-secret'),
        ];

        $this->call('POST', route('billing.webhook'), [], [], [], $headers, $raw)->assertNoContent();
        $this->call('POST', route('billing.webhook'), [], [], [], $headers, $raw)->assertNoContent();

        $payment = DB::table('billing_payments')->where('provider_payment_id', 'pay_failed_123')->first();
        $this->assertNotNull($payment);
        $this->assertSame($tenant->id, (int) $payment->tenant_id);
        $this->assertSame($subscription->id, (int) $payment->subscription_id);
        $this->assertSame('failed', $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertSame(['order_id' => 'order_failed_123', 'error_code' => 'BAD_REQUEST_ERROR'], json_decode($payment->provider_metadata, true));
        $this->assertSame(1, DB::table('billing_payments')->where('provider_payment_id', 'pay_failed_123')->count());
        $this->assertSame(0, DB::table('billing_invoices')->where('tenant_id', $tenant->id)->count());
        $updatedSubscription = DB::table('tenant_subscriptions')->where('id', $subscription->id)->first();
        $this->assertSame('past_due', $updatedSubscription->status);
        $this->assertNotNull($updatedSubscription->grace_ends_at);
        $this->context->set($tenant);
        $this->assertFalse(app(BillingService::class)->canStartCheckout($tenant));

        $subscription->refresh()->update(['grace_ends_at' => now()->subSecond()]);
        $this->assertFalse(app(BillingService::class)->canWrite($tenant));
        $this->assertTrue(app(BillingService::class)->canStartCheckout($tenant));
    }

    public function test_invalid_grace_duration_is_rejected_and_zero_means_immediate_expiry(): void
    {
        [$tenant, , $subscription] = $this->workspace('Invalid grace config', 'invalid-grace-config@example.test', now()->addDays(10), null);
        $this->context->set($tenant);
        $subscription->update(['status' => 'past_due', 'past_due_started_at' => now()->subMinute()]);
        $billing = app(BillingService::class);

        foreach (['invalid', -1] as $graceDays) {
            config(['billing.grace_days' => $graceDays]);
            try {
                $billing->graceEndsAt($subscription->refresh());
                $this->fail('Invalid grace configuration should not be accepted.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame('BILLING_GRACE_DAYS must be a non-negative integer.', $exception->getMessage());
            }
        }

        config(['billing.grace_days' => 0]);
        $this->assertFalse($billing->canWrite($tenant));
        $this->assertSame($subscription->past_due_started_at->timestamp, $billing->graceEndsAt($subscription->refresh())->timestamp);
    }

    public function test_configured_grace_period_uses_provider_failure_time_when_webhook_is_delayed(): void
    {
        [$tenant, , $subscription] = $this->workspace('Delayed failure', 'delayed-failure@example.test', now()->addDays(10), null, 'sub_delayed_failure');
        $this->context->set($tenant);
        $subscription->update(['status' => 'active']);
        config(['billing.grace_days' => 2, 'billing.razorpay.webhook_secret' => 'webhook-secret']);
        $failureAt = now()->subDays(3);

        $this->sendSignedWebhook('evt_delayed_failure', [
            'event' => 'payment.failed',
            'created_at' => $failureAt->timestamp,
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_delayed_failure']],
                'payment' => ['entity' => [
                    'id' => 'pay_delayed_failure', 'subscription_id' => 'sub_delayed_failure',
                    'amount' => 11800, 'currency' => 'INR',
                ]],
            ],
        ])->assertNoContent();

        $this->context->set($tenant);
        $subscription->refresh();
        $this->assertSame('past_due', $subscription->status);
        $this->assertSame($failureAt->copy()->addDays(2)->timestamp, $subscription->grace_ends_at->timestamp);
        $this->assertFalse(app(BillingService::class)->canWrite($tenant));
        $this->assertTrue(app(BillingService::class)->canStartCheckout($tenant));
    }

    public function test_missing_or_malformed_failure_timestamp_anchors_grace_to_receipt_time(): void
    {
        config(['billing.grace_days' => 2, 'billing.razorpay.webhook_secret' => 'webhook-secret']);

        foreach ([null, 'invalid-provider-time'] as $index => $providerTimestamp) {
            $providerSubscriptionId = 'sub_invalid_failure_time_'.$index;
            [$tenant, , $subscription] = $this->workspace(
                'Invalid failure time '.$index,
                'invalid-failure-time-'.$index.'@example.test',
                now()->addDays(10),
                null,
                $providerSubscriptionId
            );
            $this->context->set($tenant);
            $subscription->update(['status' => 'active']);

            $payload = [
                'event' => 'payment.failed',
                'payload' => [
                    'subscription' => ['entity' => ['id' => $providerSubscriptionId]],
                    'payment' => ['entity' => [
                        'id' => 'pay_invalid_failure_time_'.$index,
                        'subscription_id' => $providerSubscriptionId,
                        'amount' => 11800,
                        'currency' => 'INR',
                    ]],
                ],
            ];
            if ($providerTimestamp !== null) $payload['created_at'] = $providerTimestamp;
            $receivedAt = now()->timestamp;

            $this->sendSignedWebhook('evt_invalid_failure_time_'.$index, $payload)->assertNoContent();

            $this->context->set($tenant);
            $subscription->refresh();
            $this->assertSame('past_due', $subscription->status);
            $this->assertEqualsWithDelta($receivedAt + 2 * 86400, $subscription->grace_ends_at->timestamp, 2);
        }
    }

    public function test_grace_configured_after_failure_uses_original_provider_failure_time(): void
    {
        [$tenant, $user, $subscription] = $this->workspace('Deferred grace', 'deferred-grace@example.test', now()->addDays(10), null, 'sub_deferred_grace');
        $this->context->set($tenant);
        $subscription->update(['status' => 'active']);
        config(['billing.grace_days' => null, 'billing.razorpay.webhook_secret' => 'webhook-secret']);
        $failureAt = now()->subDays(3);

        $this->sendSignedWebhook('evt_deferred_grace', [
            'event' => 'payment.failed', 'created_at' => $failureAt->timestamp,
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_deferred_grace']],
                'payment' => ['entity' => ['id' => 'pay_deferred_grace', 'subscription_id' => 'sub_deferred_grace', 'amount' => 11800, 'currency' => 'INR']],
            ],
        ])->assertNoContent();

        $this->context->set($tenant);
        $billing = app(BillingService::class);
        $this->assertTrue($billing->canWrite($tenant));
        $this->assertNull(DB::table('tenant_subscriptions')->where('id', $subscription->id)->value('grace_ends_at'));
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('No grace period is configured')->assertSee('write access remains enabled');

        config(['billing.grace_days' => 2]);
        $this->assertFalse($billing->canWrite($tenant));
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))
            ->assertOk()->assertSee('Payment overdue')->assertSee('Grace period ends:');
    }

    public function test_repeated_failures_do_not_reset_the_original_past_due_time_when_grace_is_unset(): void
    {
        [$tenant, , $subscription] = $this->workspace('Repeated failures', 'repeated-failures@example.test', now()->addDays(10), null, 'sub_repeated_failures');
        $this->context->set($tenant);
        $subscription->update(['status' => 'active']);
        config(['billing.grace_days' => null, 'billing.razorpay.webhook_secret' => 'webhook-secret']);
        $firstFailureAt = now()->subDays(5)->startOfMinute();
        $secondFailureAt = now()->subDays(2)->startOfMinute();

        foreach ([
            ['event_id' => 'evt_first_failure', 'payment_id' => 'pay_first_failure', 'created_at' => $firstFailureAt],
            ['event_id' => 'evt_second_failure', 'payment_id' => 'pay_second_failure', 'created_at' => $secondFailureAt],
        ] as $failure) {
            $this->sendSignedWebhook($failure['event_id'], [
                'event' => 'payment.failed',
                'created_at' => $failure['created_at']->timestamp,
                'payload' => [
                    'subscription' => ['entity' => ['id' => 'sub_repeated_failures']],
                    'payment' => ['entity' => [
                        'id' => $failure['payment_id'],
                        'subscription_id' => 'sub_repeated_failures',
                        'amount' => 11800,
                        'currency' => 'INR',
                    ]],
                ],
            ])->assertNoContent();
        }

        $this->context->set($tenant);
        $subscription->refresh();
        $this->assertSame('past_due', $subscription->status);
        $this->assertSame($secondFailureAt->timestamp, $subscription->provider_state_at->timestamp);
        $this->assertSame($firstFailureAt->timestamp, $subscription->past_due_started_at->timestamp);
        $this->assertNull($subscription->grace_ends_at);

        config(['billing.grace_days' => 3]);
        $this->assertSame($firstFailureAt->copy()->addDays(3)->timestamp, app(BillingService::class)->graceEndsAt($subscription)->timestamp);
        $this->assertFalse(app(BillingService::class)->canWrite($tenant));

        $recoveredAt = now()->addMinute();
        $this->sendSignedWebhook('evt_recovered_after_failures', [
            'event' => 'subscription.charged',
            'created_at' => $recoveredAt->timestamp,
            'payload' => ['subscription' => ['entity' => [
                'id' => 'sub_repeated_failures',
                'current_start' => $recoveredAt->timestamp,
                'current_end' => $recoveredAt->copy()->addMonth()->timestamp,
            ]]],
        ])->assertNoContent();

        $this->context->set($tenant);
        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->grace_ends_at);
        $this->assertNull($subscription->past_due_started_at);
        $this->assertTrue(app(BillingService::class)->canWrite($tenant));
    }

    public function test_webhook_subscription_lookup_overrides_an_unrelated_active_tenant_context(): void
    {
        [$tenantA] = $this->workspace('Webhook tenant A', 'webhook-a@example.test', now()->addDays(10), null, 'sub_webhook_a');
        [$tenantB] = $this->workspace('Webhook tenant B', 'webhook-b@example.test', now()->addDays(10), null, 'sub_webhook_b');
        $this->context->set($tenantA);
        $raw = json_encode([
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_webhook_b']],
                'payment' => ['entity' => ['id' => 'pay_webhook_b', 'subscription_id' => 'sub_webhook_b', 'amount' => 50000, 'currency' => 'INR']],
            ],
        ], JSON_THROW_ON_ERROR);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);
        $headers = [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_webhook_b',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, 'webhook-secret'),
        ];

        $this->call('POST', route('billing.webhook'), [], [], [], $headers, $raw)->assertNoContent();

        $payment = DB::table('billing_payments')->where('provider_payment_id', 'pay_webhook_b')->first();
        $this->assertNotNull($payment);
        $this->assertSame($tenantB->id, (int) $payment->tenant_id);
        $this->assertNotSame($tenantA->id, (int) $payment->tenant_id);
        $this->assertSame($tenantB->id, (int) DB::table('billing_events')->where('provider_event_id', 'evt_webhook_b')->value('tenant_id'));
    }

    public function test_webhook_does_not_rebind_payment_id_to_another_tenant_subscription(): void
    {
        [$tenantA, , $subscriptionA] = $this->workspace('Payment owner A', 'payment-owner-a@example.test', now()->addDays(10), null, 'sub_payment_owner_a');
        [$tenantB, , $subscriptionB] = $this->workspace('Payment owner B', 'payment-owner-b@example.test', now()->addDays(10), null, 'sub_payment_owner_b');
        $this->context->set($tenantA);
        $payment = BillingPayment::create([
            'subscription_id' => $subscriptionA->id,
            'provider_payment_id' => 'pay_shared_conflict',
            'amount_paise' => 11800,
            'currency' => 'INR',
            'status' => 'captured',
            'method' => 'card',
            'paid_at' => now()->subDay(),
        ]);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);

        $this->sendSignedWebhook('evt_conflicting_payment_binding', [
            'event' => 'subscription.charged',
            'created_at' => now()->timestamp,
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_payment_owner_b']],
                'payment' => ['entity' => [
                    'id' => 'pay_shared_conflict',
                    'subscription_id' => 'sub_payment_owner_b',
                    'amount' => 150000,
                    'currency' => 'INR',
                    'method' => 'netbanking',
                ]],
            ],
        ])->assertNoContent();

        $this->context->set($tenantA);
        $this->assertSame(1, DB::table('billing_payments')->where('provider_payment_id', 'pay_shared_conflict')->count());
        $this->assertSame($tenantA->id, (int) $payment->fresh()->tenant_id);
        $this->assertSame($subscriptionA->id, (int) $payment->fresh()->subscription_id);
        $this->assertSame(11800, (int) $payment->fresh()->amount_paise);
        $this->assertSame('captured', $payment->fresh()->status);
        $this->assertSame(0, DB::table('billing_invoices')->where('payment_id', $payment->id)->count());

        $this->context->set($tenantB);
        $this->assertSame('trialing', $subscriptionB->fresh()->status);
        $this->assertSame($tenantB->id, (int) DB::table('billing_events')->where('provider_event_id', 'evt_conflicting_payment_binding')->value('tenant_id'));
        $this->assertNotNull(DB::table('billing_events')->where('provider_event_id', 'evt_conflicting_payment_binding')->value('processed_at'));
    }

    public function test_webhook_ignores_payment_entity_linked_to_a_different_subscription_id(): void
    {
        [$tenant, , $subscriptionA] = $this->workspace('Mismatched event subscription A', 'mismatched-event-a@example.test', now()->addDays(10), null, 'sub_mismatched_event_a');
        [, , $subscriptionB] = $this->workspace('Mismatched event subscription B', 'mismatched-event-b@example.test', now()->addDays(10), null, 'sub_mismatched_event_b');
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);

        $this->sendSignedWebhook('evt_subscription_entity_mismatch', [
            'event' => 'subscription.charged',
            'created_at' => now()->timestamp,
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_mismatched_event_a']],
                'payment' => ['entity' => [
                    'id' => 'pay_mismatched_event',
                    'subscription_id' => 'sub_mismatched_event_b',
                    'amount' => 11800,
                    'currency' => 'INR',
                ]],
            ],
        ])->assertNoContent();

        $this->assertSame('trialing', $subscriptionA->fresh()->status);
        $this->assertSame('trialing', $subscriptionB->fresh()->status);
        $this->assertSame(0, DB::table('billing_payments')->where('provider_payment_id', 'pay_mismatched_event')->count());
        $event = DB::table('billing_events')->where('provider_event_id', 'evt_subscription_entity_mismatch')->first();
        $this->assertSame($tenant->id, (int) $event->tenant_id);
        $this->assertNotNull($event->processed_at);
    }

    public function test_demo_catalog_values_cannot_enable_checkout_or_issue_tax_invoices(): void
    {
        config(['app.env' => 'testing', 'billing.demo_mode' => true]);
        app(\Database\Seeders\BillingDemoSeeder::class)->run();
        $starter = Plan::where('code', 'starter')->firstOrFail();
        $this->assertSame('Starter (Demo)', $starter->name);
        $this->assertSame(49900, $starter->monthly_price_paise);
        $this->assertSame(499000, $starter->yearly_price_paise);
        $this->assertFalse(app(BillingService::class)->checkoutReady($starter, 'monthly'));

        [$tenant, $user, $subscription] = $this->workspace('Demo billing', 'demo-billing@example.test', now()->addDays(10), null, 'sub_demo_123');
        config(['billing.seller' => [
            'name' => 'DEMO ONLY', 'gstin' => 'DEMO', 'pan' => 'DEMO', 'address' => 'DEMO ONLY',
            'state_code' => '27', 'gst_rate' => 18, 'sac_code' => 'DEMO', 'price_tax_mode' => 'inclusive',
        ]]);
        $raw = json_encode([
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_demo_123']],
                'payment' => ['entity' => ['id' => 'pay_demo_123', 'subscription_id' => 'sub_demo_123', 'amount' => 10000, 'currency' => 'INR']],
            ],
        ], JSON_THROW_ON_ERROR);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);
        $headers = [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_demo_123',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, 'webhook-secret'),
        ];

        $this->call('POST', route('billing.webhook'), [], [], [], $headers, $raw)->assertNoContent();

        $this->assertSame(1, DB::table('billing_payments')->where('provider_payment_id', 'pay_demo_123')->count());
        $this->assertSame(0, DB::table('billing_invoices')->where('tenant_id', $tenant->id)->count());
        $this->assertSame('active', DB::table('tenant_subscriptions')->where('id', $subscription->id)->value('status'));
        $user->forceFill(['role' => 'staff'])->save();
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->get(route('billing.index'))
            ->assertOk()->assertSee('DEMO MODE')->assertSee('Starter (Demo)')->assertSee('499.00')
            ->assertSee('(demo only; no tax quote)')->assertDontSee('@elseif')->assertSee('data-tooltip="Billing"', false);
    }

    public function test_older_provider_webhook_cannot_overwrite_newer_subscription_state(): void
    {
        [$tenant, , $subscription] = $this->workspace('Ordered events', 'ordered-events@example.test', now()->addDays(10), null, 'sub_ordered_123');
        $this->context->set($tenant);
        $subscription->update(['status' => 'active']);
        config(['billing.razorpay.webhook_secret' => 'webhook-secret']);
        $newerAt = now()->timestamp;
        $cancelledAt = now()->addDays(20)->timestamp;
        $cancelPayload = [
            'event' => 'subscription.cancelled', 'created_at' => $newerAt,
            'payload' => ['subscription' => ['entity' => ['id' => 'sub_ordered_123', 'current_end' => $cancelledAt]]],
        ];
        $this->sendSignedWebhook('evt_ordered_cancel', $cancelPayload)->assertNoContent();

        $olderAt = now()->subDay()->timestamp;
        $failurePayload = [
            'event' => 'payment.failed', 'created_at' => $olderAt,
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_ordered_123']],
                'payment' => ['entity' => ['id' => 'pay_ordered_old', 'subscription_id' => 'sub_ordered_123', 'amount' => 50000, 'currency' => 'INR']],
            ],
        ];
        $this->sendSignedWebhook('evt_ordered_old_failure', $failurePayload)->assertNoContent();

        $updatedSubscription = DB::table('tenant_subscriptions')->where('id', $subscription->id)->first();
        $this->assertSame('cancelled', $updatedSubscription->status);
        $this->assertSame($newerAt, now()->parse($updatedSubscription->provider_state_at)->timestamp);
        $this->assertSame('failed', DB::table('billing_payments')->where('provider_payment_id', 'pay_ordered_old')->value('status'));
        $this->assertSame($tenant->id, DB::table('billing_payments')->where('provider_payment_id', 'pay_ordered_old')->value('tenant_id'));
    }

    public function test_explicit_plan_feature_denial_redirects_to_billing(): void
    {
        [$tenant, $user] = $this->workspace('No Reports', 'no-reports@example.test', now()->addDays(10), null);
        $this->context->set($tenant);
        Plan::where('code', 'trial')->update(['gst_reports' => false]);
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->get(route('reports.gst'))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('billing_feature');
    }

    private function workspace(string $name, string $email, $trialEndsAt, ?int $invoiceLimit, ?string $providerId = null): array
    {
        $tenant = Tenant::create(['name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(), 'status' => 'active', 'state_code' => '27', 'trial_ends_at' => $trialEndsAt, 'onboarding_completed_at' => now()]);
        $user = User::create(['name' => $name.' owner', 'email' => $email, 'password' => 'password', 'role' => 'admin', 'email_verified_at' => now()]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $tenant->users()->attach($user->id, ['role' => 'owner']);
        $this->context->set($tenant);
        $plan = Plan::where('code', 'trial')->firstOrFail();
        if ($invoiceLimit !== null) $plan->update(['monthly_invoice_limit' => $invoiceLimit]);
        $subscription = TenantSubscription::create([
            'plan_id' => $plan->id, 'status' => 'trialing', 'trial_ends_at' => $trialEndsAt,
            'razorpay_subscription_id' => $providerId,
        ]);
        $this->context->clear();
        return [$tenant, $user, $subscription];
    }

    private function sendSignedWebhook(string $eventId, array $payload)
    {
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        return $this->call('POST', route('billing.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_EVENT_ID' => $eventId,
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, 'webhook-secret'),
        ], $raw);
    }
}
