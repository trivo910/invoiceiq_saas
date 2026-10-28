<?php

namespace App\Http\Controllers;

use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Plan;
use App\Services\BillingService;
use App\Support\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(BillingService $billing, TenantContext $context)
    {
        $tenant = $context->current();
        $subscription = $billing->subscription($tenant);
        $canWrite = $billing->canWrite($tenant);
        $graceEndsAt = $subscription ? $billing->graceEndsAt($subscription) : null;
        $statusLabel = match ($subscription?->status) {
            'trialing' => $subscription->trial_ends_at?->isFuture() ? 'Trial' : 'Trial expired',
            'active' => $subscription->plan?->code === 'legacy'
                ? 'Active'
                : (($subscription->current_period_ends_at?->isFuture() ?? false) ? 'Active' : 'Subscription ended'),
            'past_due' => ($graceEndsAt?->isFuture() ?? true) ? 'Past due' : 'Payment overdue',
            'cancelled' => $subscription->current_period_ends_at?->isFuture() ? 'Cancelled (access through period end)' : 'Cancelled',
            'completed' => $subscription->current_period_ends_at?->isFuture() ? 'Completed (access through period end)' : 'Completed',
            'paused' => $subscription->current_period_ends_at?->isFuture() ? 'Paused (access through period end)' : 'Paused',
            default => $subscription ? ucfirst(str_replace('_', ' ', $subscription->status)) : null,
        };
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $pendingCheckout = $tenant->subscriptions()->where('status', 'pending')->with('plan')->latest('id')->first();
        $failedCheckout = $tenant->subscriptions()->where('status', 'checkout_failed')->with('plan')->latest('id')->first();
        $showFailedCheckoutNotice = $failedCheckout
            && ! $pendingCheckout
            && (! $subscription || $failedCheckout->id > $subscription->id);
        $checkoutReady = $plans->mapWithKeys(fn (Plan $plan) => [$plan->id => [
            'monthly' => $billing->checkoutReady($plan, 'monthly'),
            'yearly' => $billing->checkoutReady($plan, 'yearly'),
        ]])->all();
        $pendingInterval = $pendingCheckout?->billing_interval;
        $pendingUrl = $pendingCheckout?->provider_metadata['short_url'] ?? null;
        $buyerStateConfigured = trim((string) $tenant->state_code) !== '';
        $webhookReady = $billing->webhookConfigured();
        $canRecoverPendingCheckout = $pendingCheckout
            && (! is_string($pendingUrl) || ! str_starts_with($pendingUrl, 'https://rzp.io/'))
            && $webhookReady
            && $pendingCheckout->plan
            && in_array($pendingInterval, ['monthly', 'yearly'], true)
            && $buyerStateConfigured
            && $billing->canStartCheckout($tenant)
            && ($checkoutReady[$pendingCheckout->plan_id][$pendingInterval] ?? false);
        return view('billing.index', [
            'subscription' => $subscription,
            'statusLabel' => $statusLabel,
            'graceEndsAt' => $graceEndsAt,
            'canWrite' => $canWrite,
            'plans' => $plans,
            'usage' => $billing->usage($tenant),
            'payments' => BillingPayment::latest()->limit(25)->get(),
            'invoices' => BillingInvoice::latest('issued_at')->limit(25)->get(),
            'pendingCheckout' => $pendingCheckout,
            'failedCheckout' => $failedCheckout,
            'showFailedCheckoutNotice' => $showFailedCheckoutNotice,
            'canRecoverPendingCheckout' => $canRecoverPendingCheckout,
            'buyerStateConfigured' => $buyerStateConfigured,
            'webhookReady' => $webhookReady,
            'checkoutReady' => $checkoutReady,
            'canCheckout' => $buyerStateConfigured && $billing->canStartCheckout($tenant) && ! $tenant->subscriptions()->where('status', 'pending')->exists(),
        ]);
    }

    public function checkout(Request $request, Plan $plan, BillingService $billing, TenantContext $context)
    {
        $data = $request->validate(['interval' => ['required', 'in:monthly,yearly']]);
        $tenant = $context->current();
        $pending = $tenant->subscriptions()->where('status', 'pending')->latest('id')->first();
        $pendingUrl = $pending?->provider_metadata['short_url'] ?? null;
        if ($pending) {
            if (! $billing->webhookConfigured()) {
                return redirect()->route('billing.index')->with(
                    'billing_unavailable',
                    'Checkout is temporarily unavailable until webhook authentication is configured.'
                );
            }
        }
        abort_unless(trim((string) $tenant->state_code) !== '', 422, 'Complete the workspace state code before starting a paid subscription.');
        if ($pending && is_string($pendingUrl) && str_starts_with($pendingUrl, 'https://rzp.io/')) return redirect()->away($pendingUrl);
        abort_unless($billing->canStartCheckout($tenant), 409, 'Changing an active subscription is unavailable until billing change rules are configured.');
        abort_unless($billing->checkoutReady($plan, $data['interval']), 422, 'Checkout is not configured for this plan.');

        $subscription = $billing->createCheckout($tenant, $plan, $data['interval']);
        $url = $subscription->provider_metadata['short_url'] ?? null;
        abort_unless(is_string($url) && str_starts_with($url, 'https://rzp.io/'), 502, 'The payment provider did not return a valid checkout link.');
        return redirect()->away($url);
    }

    public function invoice(BillingInvoice $invoice)
    {
        return Pdf::loadView('billing.invoice', ['invoice' => $invoice])->download($invoice->number.'.pdf');
    }
}
