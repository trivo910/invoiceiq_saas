@extends('layouts.app')
@section('title', 'Billing')
@section('content')
<div class="page-header"><div><h1>Billing</h1><p class="text-muted">Plan, trial, workspace usage and payment history.</p></div></div>
@if(session('billing_limit'))<div class="flash flash-warning">{{ session('billing_limit') }}</div>@endif
@if(session('billing_read_only'))<div class="flash flash-error">{{ session('billing_read_only') }}</div>@endif
@if(session('billing_feature'))<div class="flash flash-warning">{{ session('billing_feature') }}</div>@endif
<section class="card" style="padding:20px;margin-bottom:20px">
    <h2 style="margin-top:0">Current plan</h2>
    @if($subscription)
        <p><strong>{{ $subscription->plan?->name ?? 'Plan' }}</strong> · {{ ucfirst(str_replace('_', ' ', $subscription->status)) }}</p>
        @if($subscription->status === 'trialing')<p>Trial ends: {{ $subscription->trial_ends_at?->toDayDateTimeString() ?? 'Not set' }}</p>@endif
        @if($subscription->current_period_ends_at)<p>Next renewal: {{ $subscription->current_period_ends_at->toDayDateTimeString() }}</p>@endif
        @if($subscription->status === 'past_due' && $subscription->grace_ends_at)<p>Grace period ends: {{ $subscription->grace_ends_at->toDayDateTimeString() }}</p>@endif
        <p>Invoices this month: {{ $usage['invoices'] }}{{ $subscription->plan?->monthly_invoice_limit !== null ? ' / '.$subscription->plan->monthly_invoice_limit : '' }}</p>
        <p>Members: {{ $usage['members'] }}{{ $subscription->plan?->member_limit !== null ? ' / '.$subscription->plan->member_limit : '' }}</p>
    @else<p>No plan is assigned. Contact support.</p>@endif
</section>
@if($pendingCheckout && is_string($pendingCheckout->provider_metadata['short_url'] ?? null) && str_starts_with($pendingCheckout->provider_metadata['short_url'], 'https://rzp.io/'))
<section class="card" style="padding:20px;margin-bottom:20px"><h2>Pending checkout</h2><p>Finish the pending subscription checkout to activate the selected plan.</p><a class="btn btn-primary" href="{{ $pendingCheckout->provider_metadata['short_url'] }}" rel="noopener noreferrer">Continue checkout</a></section>
@endif
<section class="card" style="padding:20px;margin-bottom:20px">
    <h2 style="margin-top:0">Available plans</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
    @foreach($plans as $plan)
        @continue($plan->code === 'legacy')
        <article style="border:1px solid var(--border);border-radius:12px;padding:16px">
            <h3>{{ $plan->name }}</h3><p>{{ $plan->description }}</p>
            <p>Invoices: {{ $plan->monthly_invoice_limit ?? 'No configured limit' }} · Members: {{ $plan->member_limit ?? 'No configured limit' }}</p>
            @if($plan->monthly_price_paise !== null)
                <p>Monthly: ₹{{ number_format($plan->monthly_price_paise / 100, 2) }} {{ config('billing.seller.price_tax_mode') === 'exclusive' ? '+ GST' : '(tax included)' }}</p>
                @if($canCheckout && $checkoutReady[$plan->id]['monthly'])
                    <form method="POST" action="{{ route('billing.checkout', $plan) }}">@csrf<input type="hidden" name="interval" value="monthly"><button class="btn btn-primary">Continue to monthly checkout</button></form>
                @endif
            @else<p>Pricing has not been configured.</p>@endif
            @if($plan->yearly_price_paise !== null)
                <p>Yearly: ₹{{ number_format($plan->yearly_price_paise / 100, 2) }} {{ config('billing.seller.price_tax_mode') === 'exclusive' ? '+ GST' : '(tax included)' }}</p>
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
