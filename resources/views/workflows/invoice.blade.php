@extends('layouts.app')
@section('title', 'Invoice sharing and delivery')
@section('content')
<div class="page" style="max-width:1000px">
@if($errors->any())<div class="flash flash-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="page-head"><h1 class="page-title">{{ $invoice->number }}: sharing and email</h1><p>Public links are view-only. Anyone with a valid link can view this invoice PDF until it expires or is revoked.</p></div>
@if(session('invoice_share_url'))<div class="card card-body" role="status"><label for="newShareLink">Copy the new invoice link</label><input class="form-control" id="newShareLink" readonly value="{{ session('invoice_share_url') }}"><small>This URL is shown once. Treat it as private.</small></div>@endif
<section class="card" style="margin:16px 0"><div class="card-body"><h2>Create invoice link</h2><form method="post" action="{{ route('invoices.share', $invoice) }}">@csrf<label for="shareDays">Expires after days (1-90)</label><input id="shareDays" class="form-control" type="number" name="days" min="1" max="90" value="7" required><button class="btn btn-primary" type="submit">Create view-only link</button></form><small>Draft and cancelled invoices cannot be shared.</small>
@foreach($links as $link)<div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:14px"><span>Link #{{ $link->id }} — {{ $link->revoked_at ? 'Revoked' : ($link->expires_at->isFuture() ? 'Active' : 'Expired') }} — expires {{ $link->expires_at->format('d M Y H:i') }}</span>@if(! $link->revoked_at)<form method="post" action="{{ route('invoices.share.revoke', [$invoice, $link]) }}">@csrf @method('delete')<button class="btn btn-danger btn-sm">Revoke</button></form>@endif</div>@endforeach
</div></section>
<section class="card" style="margin:16px 0"><div class="card-body"><h2>Email invoice</h2><form method="post" action="{{ route('invoices.email', $invoice) }}">@csrf
<label for="mailTo">Recipient email</label><input id="mailTo" name="to_email" type="email" class="form-control" value="{{ old('to_email', $invoice->customer?->email) }}" required>
<label for="mailCc">CC (optional)</label><input id="mailCc" name="cc_email" type="email" class="form-control">
<label for="mailSubject">Subject</label><input id="mailSubject" name="email_subject" maxlength="500" class="form-control" value="Invoice {{ $invoice->number }}" required>
<label for="mailBody">Message</label><textarea id="mailBody" name="email_body" rows="4" maxlength="50000" class="form-control" required>{{ old('email_body', 'Please find your invoice attached.') }}</textarea><button class="btn btn-primary" type="submit">Send invoice</button></form>
<h2 style="margin-top:20px">Email history</h2><div style="overflow:auto"><table class="table"><thead><tr><th>Requested</th><th>Recipient</th><th>Subject</th><th>Status</th><th>Sent</th></tr></thead><tbody>@forelse($deliveries as $delivery)<tr><td>{{ $delivery->created_at->format('d M Y H:i') }}</td><td>{{ $delivery->payload['to_email'] }}</td><td>{{ $delivery->payload['email_subject'] }}</td><td>{{ ucfirst($delivery->status) }}</td><td>{{ $delivery->sent_at?->format('d M Y H:i') ?? '—' }}</td></tr>@empty<tr><td colspan="5">No email requests yet.</td></tr>@endforelse</tbody></table></div></div></section>
</div>
@endsection
