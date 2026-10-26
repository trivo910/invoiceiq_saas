<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\TenantSubscription;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RazorpayWebhookService
{
    public function __construct(private TenantContext $context)
    {
    }

    public function process(string $eventId, array $payload): void
    {
        DB::transaction(function () use ($eventId, $payload) {
            $inserted = DB::table('billing_events')->insertOrIgnore([
                'provider_event_id' => $eventId,
                'event_type' => (string) ($payload['event'] ?? 'unknown'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $inserted) return;

            $subscriptionData = data_get($payload, 'payload.subscription.entity', []);
            $paymentData = data_get($payload, 'payload.payment.entity', []);
            $subscriptionData = is_array($subscriptionData) ? $subscriptionData : [];
            $paymentData = is_array($paymentData) ? $paymentData : [];
            $providerSubscriptionId = $subscriptionData['id'] ?? $paymentData['subscription_id'] ?? null;
            $subscriptionRow = $providerSubscriptionId
                ? DB::table('tenant_subscriptions')->where('razorpay_subscription_id', $providerSubscriptionId)->lockForUpdate()->first()
                : null;
            if (! $subscriptionRow) {
                DB::table('billing_events')->where('provider_event_id', $eventId)->update(['processed_at' => now(), 'updated_at' => now()]);
                return;
            }

            $tenant = \App\Models\Tenant::find($subscriptionRow->tenant_id);
            if (! $tenant) throw new RuntimeException('Webhook subscription tenant no longer exists.');
            $this->context->set($tenant);
            try {
                $subscription = TenantSubscription::query()->findOrFail($subscriptionRow->id);
                $event = (string) ($payload['event'] ?? '');
                if (in_array($event, ['subscription.activated', 'subscription.charged', 'subscription.resumed'], true)) {
                    $subscription->update([
                        'status' => 'active',
                        'current_period_starts_at' => isset($subscriptionData['current_start']) ? now()->setTimestamp((int) $subscriptionData['current_start']) : $subscription->current_period_starts_at,
                        'current_period_ends_at' => isset($subscriptionData['current_end']) ? now()->setTimestamp((int) $subscriptionData['current_end']) : $subscription->current_period_ends_at,
                        'grace_ends_at' => null,
                    ]);
                } elseif (in_array($event, ['subscription.pending', 'subscription.halted'], true) && $subscription->status !== 'pending') {
                    $graceDays = config('billing.grace_days');
                    $subscription->update([
                        'status' => 'past_due',
                        'grace_ends_at' => $subscription->grace_ends_at ?? ($graceDays === null ? null : now()->addDays((int) $graceDays)),
                    ]);
                } elseif ($event === 'subscription.cancelled') {
                    $endsAt = isset($subscriptionData['current_end']) ? now()->setTimestamp((int) $subscriptionData['current_end']) : $subscription->current_period_ends_at;
                    $subscription->update(['status' => 'cancelled', 'current_period_ends_at' => $endsAt, 'ended_at' => $endsAt && $endsAt->isPast() ? now() : null]);
                } elseif ($event === 'subscription.completed') {
                    $endsAt = $subscription->current_period_ends_at;
                    $subscription->update(['status' => 'cancelled', 'ended_at' => $endsAt && $endsAt->isFuture() ? $endsAt : now()]);
                }

                if (($event === 'payment.captured' || $event === 'subscription.charged') && ! empty($paymentData['id'])) {
                    $payment = BillingPayment::firstOrCreate(
                        ['provider_payment_id' => $paymentData['id']],
                        [
                            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id,
                            'amount_paise' => (int) ($paymentData['amount'] ?? 0), 'currency' => $paymentData['currency'] ?? 'INR',
                            'status' => 'captured', 'method' => $paymentData['method'] ?? null,
                            'paid_at' => isset($paymentData['created_at']) ? now()->setTimestamp((int) $paymentData['created_at']) : now(),
                            'provider_metadata' => ['order_id' => $paymentData['order_id'] ?? null],
                        ]
                    );
                    $this->issueInvoiceIfConfigured($tenant, $payment);
                }

                DB::table('billing_events')->where('provider_event_id', $eventId)->update([
                    'tenant_id' => $tenant->id, 'processed_at' => now(), 'updated_at' => now(),
                ]);
            } finally {
                $this->context->clear();
            }
        });
    }

    private function issueInvoiceIfConfigured(\App\Models\Tenant $tenant, BillingPayment $payment): void
    {
        $seller = config('billing.seller');
        if (! in_array($seller['price_tax_mode'], ['inclusive', 'exclusive'], true)
            || ! $seller['name'] || ! $seller['gstin'] || ! $seller['pan'] || ! $seller['address']
            || ! $seller['state_code'] || ! $seller['gst_rate'] || ! $seller['sac_code'] || ! $tenant->state_code) return;
        if ((float) $seller['gst_rate'] <= 0 || (float) $seller['gst_rate'] > 100) return;
        if (BillingInvoice::where('payment_id', $payment->id)->exists()) return;

        $gross = $payment->amount_paise;
        $rate = (float) $seller['gst_rate'];
        $subtotal = $seller['price_tax_mode'] === 'exclusive'
            ? (int) ($payment->subscription?->plan?->getAttribute($payment->subscription->billing_interval.'_price_paise') ?? round($gross * 100 / (100 + $rate)))
            : (int) round($gross * 100 / (100 + $rate));
        $tax = $gross - $subtotal;
        $taxBreakdown = (string) $seller['state_code'] === (string) $tenant->state_code
            ? ['CGST' => (int) round($subtotal * ($rate / 2) / 100), 'SGST' => $tax - (int) round($subtotal * ($rate / 2) / 100)]
            : ['IGST' => $tax];
        BillingInvoice::create([
            'payment_id' => $payment->id,
            'number' => 'SaaS-'.$payment->id,
            'subtotal_paise' => $subtotal, 'tax_paise' => $tax, 'total_paise' => $gross,
            'currency' => $payment->currency, 'tax_breakdown' => $taxBreakdown,
            'seller_snapshot' => $seller + ['sac_code' => $seller['sac_code']],
            'buyer_snapshot' => ['name' => $tenant->name, 'gstin' => $tenant->gstin, 'pan' => $tenant->pan, 'address' => $tenant->address, 'state_code' => $tenant->state_code],
            'issued_at' => $payment->paid_at ?? now(),
        ]);
    }
}
