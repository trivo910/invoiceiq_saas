@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="page">
    <div class="page-head flex justify-between items-center">
        <div>
            <div class="page-title">Dashboard</div>
            <div class="page-subtitle">{{ now()->format('l, d F Y') }}</div>
        </div>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary">+ New Invoice</a>
    </div>

    {{-- Stats --}}
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-label">Total Invoiced</div>
            <div class="stat-value">{{ fmt_inr($stats['total_invoiced']) }}</div>
        </div>
        <div class="stat-card ok">
            <div class="stat-label">Paid</div>
            <div class="stat-value">{{ fmt_inr($stats['total_paid']) }}</div>
        </div>
        <div class="stat-card acc">
            <div class="stat-label">Outstanding</div>
            <div class="stat-value">{{ fmt_inr($stats['total_outstanding']) }}</div>
        </div>
        <div class="stat-card err">
            <div class="stat-label">Overdue</div>
            <div class="stat-value">{{ fmt_inr($stats['total_overdue']) }}</div>
        </div>
    </div>

    {{-- Quick info row --}}
    <div class="quick-info-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:28px;">
        <div class="card" style="padding:16px 20px;">
            <div class="stat-label">Total Invoices</div>
            <div style="font-size:20px;font-weight:600;color:var(--t1);margin-top:4px;">{{ $stats['invoice_count'] }}</div>
        </div>
        <div class="card" style="padding:16px 20px;">
            <div class="stat-label">Customers</div>
            <div style="font-size:20px;font-weight:600;color:var(--t1);margin-top:4px;">{{ $stats['customer_count'] }}</div>
        </div>
        <div class="card" style="padding:16px 20px;">
            <div class="stat-label">Drafts Pending</div>
            <div style="font-size:20px;font-weight:600;color:var(--warn);margin-top:4px;">{{ $stats['draft_count'] }}</div>
        </div>
    </div>

    {{-- Recent invoices --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">Recent Invoices</div>
            <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-ghost">View All →</a>
        </div>
        @if($recent->isEmpty())
            <div style="padding:50px 20px;text-align:center;color:var(--t4);">
                No invoices yet.
                <a href="{{ route('invoices.create') }}" style="color:var(--accent);text-decoration:none;font-weight:500;"> Create your first invoice →</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th class="r">Amount</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recent as $inv)
                            @php $eff = $inv->effective_status; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('invoices.edit', $inv) }}" style="color:var(--accent);font-weight:600;text-decoration:none;">{{ $inv->number }}</a>
                                </td>
                                <td>{{ $inv->customer_name ?: '—' }}</td>
                                <td style="color:var(--t3);">{{ $inv->invoice_date->format('d M Y') }}</td>
                                <td style="color:{{ $eff==='overdue' ? 'var(--err)' : 'var(--t3)' }};">
                                    {{ $inv->due_date ? $inv->due_date->format('d M Y') : '—' }}
                                </td>
                                <td class="r" style="font-weight:600;">{{ fmt_inr($inv->grand_total) }}</td>
                                <td><span class="badge badge-{{ $inv->status_color }}">{{ ucfirst($eff) }}</span></td>
                                <td>
                                    <a href="{{ route('invoices.edit', $inv) }}" class="btn btn-xs">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
