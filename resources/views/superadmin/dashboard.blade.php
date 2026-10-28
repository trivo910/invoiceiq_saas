@extends('superadmin.layout')
@section('content')
<h1>Platform overview</h1>
<div class="sa-grid">
@foreach(['total' => 'Total workspaces', 'active' => 'Active subscriptions', 'trial' => 'Current trials', 'expired' => 'Ended subscriptions', 'suspended' => 'Suspended workspaces'] as $key => $label)
    <section class="sa-panel sa-metric"><span>{{ $label }}</span><strong>{{ $stats[$key] }}</strong></section>
@endforeach
</div>
<section class="sa-panel"><h2>Catalog MRR estimate</h2><p style="font-size:28px">INR {{ number_format($stats['mrr'] / 100, 2) }}</p><small>Current active provider subscriptions at catalog values, with yearly prices divided by 12. Excludes manual grants, legacy/trial, suspended workspaces, ended access and overdue subscriptions. This is an estimate, not collected revenue; discounts and tax treatment are not adjusted.</small></section>
<section class="sa-panel"><h2>Workspace signups</h2>
<table class="sa-table"><thead><tr><th>Month</th><th>Signups</th><th>Trend</th></tr></thead><tbody>
@foreach($stats['trend'] as $point)<tr><td>{{ $point['month'] }}</td><td>{{ $point['count'] }}</td><td><meter min="0" max="{{ max(1, $stats['trend']->max('count')) }}" value="{{ $point['count'] }}" aria-label="{{ $point['month'] }} signups">{{ $point['count'] }}</meter></td></tr>@endforeach
</tbody></table></section>
@endsection
