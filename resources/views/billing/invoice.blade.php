<!doctype html><html><head><meta charset="utf-8"><title>{{ $invoice->number }}</title><style>body{font:14px sans-serif;color:#172033}h1{margin-bottom:8px}section{margin:24px 0}table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #ddd;text-align:left}</style></head><body>
<h1>Subscription tax invoice</h1><p>{{ $invoice->number }} · {{ $invoice->issued_at->format('d M Y') }}</p>
<section><h2>Seller</h2><p>{{ $invoice->seller_snapshot['name'] ?? '—' }}<br>{{ $invoice->seller_snapshot['address'] ?? '—' }}<br>GSTIN: {{ $invoice->seller_snapshot['gstin'] ?? '—' }} · PAN: {{ $invoice->seller_snapshot['pan'] ?? '—' }}</p></section>
<section><h2>Bill to</h2><p>{{ $invoice->buyer_snapshot['name'] ?? '—' }}<br>{{ $invoice->buyer_snapshot['address'] ?? '—' }}<br>GSTIN: {{ $invoice->buyer_snapshot['gstin'] ?? '—' }}</p></section>
<p>SAC: {{ $invoice->seller_snapshot['sac_code'] }}</p>
<table><tbody><tr><th>Subscription service</th><td>{{ $invoice->currency }} {{ number_format($invoice->subtotal_paise / 100, 2) }}</td></tr>@foreach($invoice->tax_breakdown ?? [] as $label => $amount)<tr><th>{{ $label }}</th><td>{{ $invoice->currency }} {{ number_format($amount / 100, 2) }}</td></tr>@endforeach<tr><th>Total</th><td>{{ $invoice->currency }} {{ number_format($invoice->total_paise / 100, 2) }}</td></tr></tbody></table>
</body></html>
