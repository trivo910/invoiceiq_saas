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
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        return view('billing.index', [
            'subscription' => $subscription,
            'plans' => $plans,
            'usage' => $billing->usage($tenant),
            'payments' => BillingPayment::latest()->limit(25)->get(),
            'invoices' => BillingInvoice::latest('issued_at')->limit(25)->get(),
            'pendingCheckout' => $tenant->subscriptions()->where('status', 'pending')->latest('id')->first(),
            'checkoutReady' => $plans->mapWithKeys(fn (Plan $plan) => [$plan->id => [
                'monthly' => $billing->checkoutReady($plan, 'monthly'),
                'yearly' => $billing->checkoutReady($plan, 'yearly'),
            ]])->all(),
            'canCheckout' => (bool) $tenant->state_code && $billing->canStartCheckout($tenant) && ! $tenant->subscriptions()->where('status', 'pending')->exists(),
        ]);
    }

    public function checkout(Request $request, Plan $plan, BillingService $billing, TenantContext $context)
    {
        $data = $request->validate(['interval' => ['required', 'in:monthly,yearly']]);
        $tenant = $context->current();
        $pending = $tenant->subscriptions()->where('status', 'pending')->latest('id')->first();
        $pendingUrl = $pending?->provider_metadata['short_url'] ?? null;
        if (is_string($pendingUrl) && str_starts_with($pendingUrl, 'https://rzp.io/')) return redirect()->away($pendingUrl);
        abort_unless($tenant->state_code, 422, 'Complete the workspace state code before starting a paid subscription.');
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
