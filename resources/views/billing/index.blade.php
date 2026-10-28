@extends('layouts.app')
@section('title', 'Billing')
@section('content')
<div class="page-header"><div><h1>Billing</h1><p class="text-muted">Plan, trial, workspace usage and payment history.</p></div></div>
@if(config('billing.demo_mode'))<div class="flash flash-warning">DEMO MODE: displayed prices, limits and the seven-day grace period are temporary examples. Checkout and subscription tax invoices are disabled.</div>@endif
@if(session('billing_limit'))<div class="flash flash-warning">{{ session('billing_limit') }}</div>@endif
@if(session('billing_read_only'))<div class="flash flash-error">{{ session('billing_read_only') }}</div>@endif
@if(session('billing_feature'))<div class="flash flash-warning">{{ session('billing_feature') }}</div>@endif
@if(session('billing_unavailable'))<div class="flash flash-warning">{{ session('billing_unavailable') }}</div>@endif
@if(! $canWrite)<div class="flash flash-error">This workspace is read-only. Your existing invoices and data remain available. Review billing options below to restore write access.</div>
@elseif($subscription?->status === 'past_due')
<div class="flash flash-warning">Payment is overdue.
    @if($graceEndsAt) Write access remains available until {{ $graceEndsAt->toDayDateTimeString() }}.
    @else No grace period is configured, so write access remains enabled until a grace policy is set.
    @endif
</div>
@endif
@if($canWrite && $subscription?->status === 'paused')<div class="flash flash-warning">The subscription is paused. Write access remains available through the paid period end{{ $subscription->current_period_ends_at ? ' on '.$subscription->current_period_ends_at->toDayDateTimeString() : '' }}.</div>@endif
<section class="card" style="padding:20px;margin-bottom:20px">
    <h2 style="margin-top:0">Current plan</h2>
    @if($subscription)
        <p><strong>{{ $subscription->plan?->name ?? 'Plan' }}</strong> · {{ $statusLabel }}</p>
        @if($subscription->status === 'trialing')<p>Trial ends: {{ $subscription->trial_ends_at?->toDayDateTimeString() ?? 'Not set' }}</p>@endif
        @if($subscription->current_period_ends_at)<p>{{ in_array($subscription->status, ['cancelled', 'completed', 'paused'], true) ? 'Access through' : 'Next renewal' }}: {{ $subscription->current_period_ends_at->toDayDateTimeString() }}</p>@endif
        @if($subscription->status === 'past_due' && $graceEndsAt)<p>Grace period ends: {{ $graceEndsAt->toDayDateTimeString() }}</p>@endif
        <p>Invoices this month: {{ $usage['invoices'] }}{{ $subscription->plan?->monthly_invoice_limit !== null ? ' / '.$subscription->plan->monthly_invoice_limit : '' }}</p>
        <p>Members: {{ $usage['members'] }}{{ $subscription->plan?->member_limit !== null ? ' / '.$subscription->plan->member_limit : '' }}</p>
    @else<p>No plan is assigned. Contact support.</p>@endif
</section>
@if($pendingCheckout && ! $webhookReady)
<section class="card" style="padding:20px;margin-bottom:20px"><h2>Pending checkout</h2><p>Checkout is temporarily unavailable because webhook authentication is not configured. Your current plan access is unchanged. Contact support before completing payment.</p></section>
@elseif($pendingCheckout && ! $buyerStateConfigured)
<section class="card" style="padding:20px;margin-bottom:20px"><h2>Pending checkout</h2><p>Complete the workspace state code before continuing this checkout so the subscription invoice can use the correct buyer tax location.</p></section>
@elseif($pendingCheckout && is_string($pendingCheckout->provider_metadata['short_url'] ?? null) && str_starts_with($pendingCheckout->provider_metadata['short_url'], 'https://rzp.io/'))
<section class="card" style="padding:20px;margin-bottom:20px"><h2>Pending checkout</h2><p>Finish the pending subscription checkout to activate the selected plan.</p><a class="btn btn-primary" href="{{ $pendingCheckout->provider_metadata['short_url'] }}" rel="noopener noreferrer">Continue checkout</a></section>
@elseif($pendingCheckout)
<section class="card" style="padding:20px;margin-bottom:20px"><h2>Pending checkout</h2><p>The saved checkout link is unavailable. Restore it from the existing provider subscription to continue.</p>
    @if($canRecoverPendingCheckout)<form method="POST" action="{{ route('billing.checkout', $pendingCheckout->plan) }}">@csrf<input type="hidden" name="interval" value="{{ $pendingCheckout->billing_interval }}"><button class="btn btn-primary">Restore checkout link</button></form>
    @else<p class="text-muted">The provider link cannot be restored with the current billing setup. Contact support to restore access.</p>@endif
</section>
@endif
@if($showFailedCheckoutNotice)
<div class="flash flash-warning">The provider halted the pending {{ $failedCheckout->plan?->name ?? 'plan' }} checkout before activation. Your current plan access is unchanged; you can start a new checkout below.</div>
@endif
@php
    $priceTaxLabel = config('billing.demo_mode')
        ? '(demo only; no tax quote)'
        : match (config('billing.seller.price_tax_mode')) {
            'exclusive' => '+ GST',
            'inclusive' => '(tax included)',
            default => '(tax treatment not configured)',
        };
@endphp
<section class="card" style="padding:20px;margin-bottom:20px">
    <h2 style="margin-top:0">Available plans</h2>
    <div class="billing-plan-grid">
    @foreach($plans as $plan)
        @continue($plan->code === 'legacy')
        <article class="billing-plan-card">
            <h3>{{ $plan->name }}</h3><p>{{ $plan->description }}</p>
            <p>Invoices: {{ $plan->monthly_invoice_limit ?? 'No configured limit' }} · Members: {{ $plan->member_limit ?? 'No configured limit' }}</p>
            @if($plan->monthly_price_paise !== null)
                <p>Monthly: ₹{{ number_format($plan->monthly_price_paise / 100, 2) }} {{ $priceTaxLabel }}</p>
                @if($canCheckout && $checkoutReady[$plan->id]['monthly'])
                    <form method="POST" action="{{ route('billing.checkout', $plan) }}">@csrf<input type="hidden" name="interval" value="monthly"><button class="btn btn-primary">Continue to monthly checkout</button></form>
                @endif
            @else<p>Pricing has not been configured.</p>@endif
            @if($plan->yearly_price_paise !== null)
                <p>Yearly: ₹{{ number_format($plan->yearly_price_paise / 100, 2) }} {{ $priceTaxLabel }}</p>
                @if($canCheckout && $checkoutReady[$plan->id]['yearly'])
                    <form method="POST" action="{{ route('billing.checkout', $plan) }}">@csrf<input type="hidden" name="interval" value="yearly"><button class="btn">Continue to yearly checkout</button></form>
                @endif
            @endif
        </article>
    @endforeach
    </div>
</section>
<section class="card" style="padding:20px;margin-bottom:20px">
    <h2 style="margin-top:0">Payment history</h2>
    @if($payments->isEmpty())<p class="text-muted">No subscription payments recorded.</p>@else
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>Amount</th><th>Status</th><th>Method</th></tr></thead><tbody>
    @foreach($payments as $payment)<tr><td>{{ $payment->paid_at?->format('d M Y') ?? $payment->created_at->format('d M Y') }}</td><td>{{ $payment->currency }} {{ number_format($payment->amount_paise / 100, 2) }}</td><td>{{ ucfirst($payment->status) }}</td><td>{{ $payment->method ?? '—' }}</td></tr>@endforeach
    </tbody></table></div>@endif
</section>
<section class="card" style="padding:20px">
    <h2 style="margin-top:0">Subscription invoices</h2>
    @if($invoices->isEmpty())<p class="text-muted">Invoices will appear after successful payments and seller tax details are configured.</p>@else
    <div class="table-wrap"><table><thead><tr><th>Number</th><th>Issued</th><th>Total</th><th></th></tr></thead><tbody>
    @foreach($invoices as $invoice)<tr><td>{{ $invoice->number }}</td><td>{{ $invoice->issued_at->format('d M Y') }}</td><td>{{ $invoice->currency }} {{ number_format($invoice->total_paise / 100, 2) }}</td><td><a href="{{ route('billing.invoices.download', $invoice) }}">Download PDF</a></td></tr>@endforeach
    </tbody></table></div>@endif
</section>
@endsection
