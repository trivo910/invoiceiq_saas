<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\BillingInvoice;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\WorkspaceRegistrationService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    public function test_expired_trial_keeps_reads_but_blocks_writes(): void
    {
        [$tenant, $user] = $this->workspace('Expired Trial', 'expired@example.test', now()->subMinute(), null);
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->get(route('billing.index'))->assertOk();
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])->post(route('invoices.store'), [])->assertRedirect(route('billing.index'))->assertSessionHas('billing_read_only');
    }

    public function test_invoice_limit_redirects_to_billing_when_reached(): void
    {
        [$tenant, $user] = $this->workspace('Limited', 'limited@example.test', now()->addDays(10), 0);
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('invoices.store'), [])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('billing_limit');
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

    public function test_checkout_requires_configured_terms_and_creates_provider_subscription(): void
    {
        [$tenant, $user, $trial] = $this->workspace('Checkout', 'checkout@example.test', now()->addDays(10), null);
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update(['is_active' => true, 'monthly_price_paise' => 150000, 'razorpay_plan_monthly_id' => 'plan_test_monthly', 'gst_reports' => true, 'custom_branding' => false, 'api_access' => false]);
        config([
            'billing.razorpay.key' => 'key_test', 'billing.razorpay.secret' => 'secret_test',
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

    public function test_successful_subscription_charge_creates_idempotent_payment_and_configured_tax_invoice(): void
    {
        [$tenant, , $subscription] = $this->workspace('Paid', 'paid@example.test', now()->addDays(10), null, 'sub_paid_123');
        $tenant->update(['gstin' => '27ABCDE1234F1Z5', 'state_code' => '27', 'address' => 'Buyer Street']);
        config(['billing.seller' => [
            'name' => 'InvoiceIQ Services Pvt Ltd', 'gstin' => '27AAAAA0000A1Z5', 'pan' => 'AAAAA0000A',
            'address' => 'Seller Street', 'state_code' => '27', 'gst_rate' => 18, 'sac_code' => '998313', 'price_tax_mode' => 'inclusive',
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

        $this->assertSame(1, DB::table('billing_payments')->where('provider_payment_id', 'pay_paid_123')->count());
        $invoice = DB::table('billing_invoices')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(11800, (int) $invoice->total_paise);
        $this->assertSame(10000, (int) $invoice->subtotal_paise);
        $this->assertSame(['CGST' => 900, 'SGST' => 900], json_decode($invoice->tax_breakdown, true));
    }

    public function test_failed_renewal_is_saved_once_without_issuing_an_invoice(): void
    {
        [$tenant, , $subscription] = $this->workspace('Failed renewal', 'failed-renewal@example.test', now()->addDays(10), null, 'sub_failed_123');
        $raw = json_encode([
            'event' => 'subscription.pending',
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
}
