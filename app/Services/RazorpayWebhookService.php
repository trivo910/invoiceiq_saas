<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\TenantSubscription;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RazorpayWebhookService
{
    public function __construct(private TenantContext $context, private BillingService $billing)
    {
    }

    public function process(string $eventId, array $payload): bool
    {
        return DB::transaction(function () use ($eventId, $payload): bool {
            $subscriptionData = data_get($payload, 'payload.subscription.entity', []);
            $paymentData = data_get($payload, 'payload.payment.entity', []);
            $subscriptionData = is_array($subscriptionData) ? $subscriptionData : [];
            $paymentData = is_array($paymentData) ? $paymentData : [];
            $providerSubscriptionId = $subscriptionData['id'] ?? $paymentData['subscription_id'] ?? null;
            $subscriptionRow = $providerSubscriptionId
                ? DB::table('tenant_subscriptions')->where('razorpay_subscription_id', $providerSubscriptionId)->lockForUpdate()->first()
                : null;

            // A webhook can beat the local insert just after the provider creates a subscription.
            // Do not consume that event; ask the provider to retry once checkout commits locally.
            if ($providerSubscriptionId && ! $subscriptionRow) return false;

            $inserted = DB::table('billing_events')->insertOrIgnore([
                'provider_event_id' => $eventId,
                'event_type' => (string) ($payload['event'] ?? 'unknown'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $inserted) return true;

            if (! $subscriptionRow) {
                DB::table('billing_events')->where('provider_event_id', $eventId)->update(['processed_at' => now(), 'updated_at' => now()]);
                return true;
            }

            $tenant = \App\Models\Tenant::find($subscriptionRow->tenant_id);
            if (! $tenant && \App\Models\Tenant::onlyTrashed()->whereKey($subscriptionRow->tenant_id)->exists()) {
                DB::table('billing_events')->where('provider_event_id', $eventId)->update(['tenant_id' => $subscriptionRow->tenant_id, 'processed_at' => now(), 'updated_at' => now()]);
                return true;
            }
            if (! $tenant) throw new RuntimeException('Webhook subscription tenant no longer exists.');
            $this->context->set($tenant);
            try {
                $subscription = TenantSubscription::query()->findOrFail($subscriptionRow->id);
                $event = (string) ($payload['event'] ?? '');
                $paymentSubscriptionId = is_string($paymentData['subscription_id'] ?? null)
                    ? $paymentData['subscription_id']
                    : null;
                if ($subscriptionData && $paymentSubscriptionId !== null
                    && $paymentSubscriptionId !== $subscriptionRow->razorpay_subscription_id) {
                    Log::warning('Razorpay webhook contained conflicting subscription IDs.', [
                        'event_id' => $eventId,
                        'tenant_id' => $tenant->id,
                        'subscription_id' => $subscription->id,
                    ]);
                    DB::table('billing_events')->where('provider_event_id', $eventId)->update([
                        'tenant_id' => $tenant->id, 'processed_at' => now(), 'updated_at' => now(),
                    ]);
                    return true;
                }
                $providerPaymentId = is_string($paymentData['id'] ?? null) && $paymentData['id'] !== ''
                    ? $paymentData['id']
                    : null;
                $existingPaymentRow = $providerPaymentId
                    ? DB::table('billing_payments')->where('provider_payment_id', $providerPaymentId)->lockForUpdate()->first()
                    : null;
                if ($existingPaymentRow && (
                    (int) $existingPaymentRow->tenant_id !== (int) $tenant->id
                    || (int) $existingPaymentRow->subscription_id !== (int) $subscription->id
                )) {
                    Log::warning('Razorpay webhook reused a payment ID bound to another billing record.', [
                        'event_id' => $eventId,
                        'provider_payment_id' => $providerPaymentId,
                        'tenant_id' => $tenant->id,
                        'subscription_id' => $subscription->id,
                    ]);
                    DB::table('billing_events')->where('provider_event_id', $eventId)->update([
                        'tenant_id' => $tenant->id, 'processed_at' => now(), 'updated_at' => now(),
                    ]);
                    return true;
                }
                $existingPayment = ! empty($paymentData['id'])
                    ? BillingPayment::where('provider_payment_id', $paymentData['id'])->first()
                    : null;
                $alreadyCapturedPayment = $existingPayment?->status === 'captured';
                $stateEvents = [
                    'subscription.activated', 'subscription.charged', 'subscription.resumed',
                    'subscription.pending', 'subscription.halted', 'payment.failed',
                    'subscription.cancelled', 'subscription.completed', 'subscription.paused',
                ];
                $providerEventAt = $this->providerTimestamp($payload['created_at'] ?? null);
                $staleStateEvent = in_array($event, $stateEvents, true)
                    && $providerEventAt
                    && $subscription->provider_state_at
                    && $providerEventAt->lt($subscription->provider_state_at);

                if (! $staleStateEvent && in_array($event, ['subscription.activated', 'subscription.charged', 'subscription.resumed'], true)) {
                    $subscription->update([
                        'status' => 'active',
                        'current_period_starts_at' => $this->providerTimestamp($subscriptionData['current_start'] ?? null) ?? $subscription->current_period_starts_at,
                        'current_period_ends_at' => $this->providerTimestamp($subscriptionData['current_end'] ?? null) ?? $subscription->current_period_ends_at,
                        'grace_ends_at' => null,
                        'past_due_started_at' => null,
                    ]);
                } elseif (! $staleStateEvent && $event === 'subscription.halted' && $subscription->status === 'pending') {
                    $subscription->update(['status' => 'checkout_failed']);
                } elseif (! $staleStateEvent && ! ($event === 'payment.failed' && $alreadyCapturedPayment)
                    && in_array($event, ['subscription.pending', 'subscription.halted', 'payment.failed'], true)
                    && ! in_array($subscription->status, ['pending', 'checkout_failed'], true)) {
                    $graceDays = config('billing.grace_days');
                    $pastDueStartedAt = $subscription->past_due_started_at
                        ?? ($subscription->status === 'past_due' ? $subscription->provider_state_at : null)
                        ?? $providerEventAt
                        ?? now();
                    $subscription->update([
                        'status' => 'past_due',
                        'past_due_started_at' => $pastDueStartedAt,
                        'grace_ends_at' => $subscription->grace_ends_at
                            ?? ($graceDays === null ? null : $pastDueStartedAt->copy()->addDays((int) $graceDays)),
                    ]);
                } elseif (! $staleStateEvent && $event === 'subscription.paused') {
                    $subscription->update([
                        'status' => 'paused',
                        'current_period_starts_at' => $this->providerTimestamp($subscriptionData['current_start'] ?? null) ?? $subscription->current_period_starts_at,
                        'current_period_ends_at' => $this->providerTimestamp($subscriptionData['current_end'] ?? null) ?? $subscription->current_period_ends_at,
                    ]);
                } elseif (! $staleStateEvent && $event === 'subscription.cancelled') {
                    $endsAt = $this->providerTimestamp($subscriptionData['current_end'] ?? null) ?? $subscription->current_period_ends_at;
                    $subscription->update(['status' => 'cancelled', 'current_period_ends_at' => $endsAt, 'ended_at' => $endsAt && $endsAt->isPast() ? now() : null]);
                } elseif (! $staleStateEvent && $event === 'subscription.completed') {
                    $endsAt = $this->providerTimestamp($subscriptionData['current_end'] ?? null) ?? $subscription->current_period_ends_at;
                    $subscription->update([
                        'status' => 'completed',
                        'current_period_ends_at' => $endsAt,
                        'ended_at' => $endsAt && $endsAt->isFuture() ? $endsAt : now(),
                    ]);
                }
                if (! $staleStateEvent && $providerEventAt && in_array($event, $stateEvents, true)) {
                    $subscription->update(['provider_state_at' => $providerEventAt]);
                }

                $captured = in_array($event, ['payment.captured', 'subscription.charged'], true);
                $failed = in_array($event, ['payment.failed', 'subscription.pending'], true);
                $amountPaise = $this->providerAmount($paymentData['amount'] ?? null);
                if (($captured || $failed) && is_string($paymentData['id'] ?? null) && $paymentData['id'] !== '' && $amountPaise !== null) {
                    $currency = strtoupper(trim((string) ($paymentData['currency'] ?? '')));
                    if (! preg_match('/^[A-Z]{3}$/D', $currency)) $currency = 'XXX';
                    $paidAt = $this->providerTimestamp($paymentData['created_at'] ?? null) ?? now();
                    $attributes = [
                        'tenant_id' => $tenant->id,
                        'subscription_id' => $subscription->id,
                        'amount_paise' => $amountPaise,
                        'currency' => $currency,
                        'status' => $captured ? 'captured' : 'failed',
                        'method' => $paymentData['method'] ?? null,
                        'paid_at' => $captured ? $paidAt : null,
                        'provider_metadata' => array_filter([
                            'order_id' => $paymentData['order_id'] ?? null,
                            'error_code' => data_get($paymentData, 'error_code'),
                        ], fn ($value) => $value !== null),
                    ];
                    $payment = $alreadyCapturedPayment
                        ? $existingPayment
                        : BillingPayment::updateOrCreate(['provider_payment_id' => $paymentData['id']], $attributes);
                    if ($captured) $this->issueInvoiceIfConfigured($tenant, $payment);
                }

                DB::table('billing_events')->where('provider_event_id', $eventId)->update([
                    'tenant_id' => $tenant->id, 'processed_at' => now(), 'updated_at' => now(),
                ]);
            } finally {
                $this->context->clear();
            }
            return true;
        });
    }

    private function issueInvoiceIfConfigured(\App\Models\Tenant $tenant, BillingPayment $payment): void
    {
        if (config('billing.demo_mode') || $payment->currency !== 'INR' || $payment->amount_paise <= 0) return;
        $seller = config('billing.seller');
        $sellerStateCode = trim((string) ($seller['state_code'] ?? ''));
        $buyerStateCode = trim((string) ($tenant->state_code ?? ''));
        if (! $this->billing->taxDetailsConfigured() || $sellerStateCode === '' || $buyerStateCode === '') return;
        if (BillingInvoice::where('payment_id', $payment->id)->exists()) return;

        $gross = $payment->amount_paise;
        $rate = (float) $seller['gst_rate'];
        $subscription = $payment->subscription;
        $priceAttribute = $subscription && in_array($subscription->billing_interval, ['monthly', 'yearly'], true)
            ? $subscription->billing_interval.'_price_paise'
            : null;
        $subtotal = $priceAttribute ? $subscription->plan?->getAttribute($priceAttribute) : null;
        if (! is_numeric($subtotal) || (int) $subtotal <= 0) return;
        $subtotal = (int) $subtotal;
        $expectedGross = $seller['price_tax_mode'] === 'exclusive'
            ? $subtotal + (int) round($subtotal * $rate / 100)
            : $subtotal;
        if ($gross !== $expectedGross) return;
        if ($seller['price_tax_mode'] === 'inclusive') {
            $subtotal = (int) round($gross * 100 / (100 + $rate));
        }
        $tax = $gross - $subtotal;
        $taxBreakdown = $sellerStateCode === $buyerStateCode
            ? ['CGST' => (int) round($subtotal * ($rate / 2) / 100), 'SGST' => $tax - (int) round($subtotal * ($rate / 2) / 100)]
            : ['IGST' => $tax];
        BillingInvoice::create([
            'payment_id' => $payment->id,
            'number' => 'SaaS-'.$payment->id,
            'subtotal_paise' => $subtotal, 'tax_paise' => $tax, 'total_paise' => $gross,
            'currency' => $payment->currency, 'tax_breakdown' => $taxBreakdown,
            'seller_snapshot' => array_merge($seller, ['state_code' => $sellerStateCode, 'sac_code' => $seller['sac_code']]),
            'buyer_snapshot' => ['name' => $tenant->name, 'gstin' => $tenant->gstin, 'pan' => $tenant->pan, 'address' => $tenant->address, 'state_code' => $buyerStateCode],
            'issued_at' => $payment->paid_at ?? now(),
        ]);
    }

    private function providerTimestamp(mixed $timestamp): ?\Illuminate\Support\Carbon
    {
        if (is_int($timestamp)) {
            $seconds = $timestamp;
        } elseif (is_string($timestamp) && preg_match('/^\d+$/D', trim($timestamp))) {
            $seconds = (int) trim($timestamp);
        } else {
            return null;
        }

        if ($seconds <= 0) return null;

        try {
            return now()->setTimestamp($seconds);
        } catch (\Throwable) {
            return null;
        }
    }

    private function providerAmount(mixed $amount): ?int
    {
        if (is_int($amount)) {
            return $amount >= 0 ? $amount : null;
        }

        if (! is_string($amount) || ! preg_match('/^\d+$/D', trim($amount))) return null;
        $normalized = trim($amount);
        if (strlen($normalized) > strlen((string) PHP_INT_MAX)
            || (strlen($normalized) === strlen((string) PHP_INT_MAX) && strcmp($normalized, (string) PHP_INT_MAX) > 0)) {
            return null;
        }

        return (int) $normalized;
    }
}
