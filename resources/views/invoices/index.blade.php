@extends('layouts.app')
@section('title', 'Invoices')

@section('content')
<div class="page">
    <div class="page-head flex justify-between items-center">
        <div>
            <div class="page-title">Invoices</div>
            <div class="page-subtitle">{{ $invoices->total() }} invoices total</div>
        </div>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary">+ New Invoice</a>
    </div>

    {{-- Toolbar --}}
    <div class="toolbar">
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex:1;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by invoice # or customer…"
                class="form-control" style="max-width:300px;">
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" style="width:150px;">
            <span style="color:var(--t4);font-size:12px;">to</span>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" style="width:150px;">
            <button type="submit" class="btn btn-sm">Filter</button>
            @if(request()->hasAny(['q','from','to','status']))
                <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-ghost">Clear</a>
            @endif
        </form>
        <div class="filter-pills">
            @foreach(['all'=>'All','draft'=>'Draft','sent'=>'Sent','paid'=>'Paid','overdue'=>'Overdue','cancelled'=>'Cancelled'] as $val=>$label)
                <a href="{{ route('invoices.index', array_merge(request()->except('status','page'), ['status'=>$val])) }}"
                   class="filter-pill {{ (request('status',$val==='all'?'':null)===$val || ($val==='all'&&!request('status'))) ? 'active' : '' }}">
                   {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="card">
        @if($invoices->isEmpty())
            <div style="padding:60px 20px;text-align:center;color:var(--t4);">No invoices found.</div>
        @else
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th class="r">Sub-total</th>
                            <th class="r">Grand Total</th>
                            <th>Status</th>
                            <th class="c">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $inv)
                            @php $eff = $inv->effective_status; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('invoices.edit', $inv) }}" style="color:var(--accent);font-weight:600;text-decoration:none;">
                                        {{ $inv->number }}
                                    </a>
                                </td>
                                <td style="font-weight:500;">{{ $inv->customer_name ?: '—' }}</td>
                                <td style="color:var(--t3);">{{ $inv->invoice_date->format('d M Y') }}</td>
                                <td style="color:{{ $eff==='overdue' ? 'var(--err)' : 'var(--t3)' }};">
                                    {{ $inv->due_date ? $inv->due_date->format('d M Y') : '—' }}
                                </td>
                                <td class="r">{{ fmt_inr($inv->sub_total) }}</td>
                                <td class="r" style="font-weight:600;">{{ fmt_inr($inv->grand_total) }}</td>
                                <td>
                                    <span class="badge badge-{{ $inv->status_color }}">{{ ucfirst($eff) }}</span>
                                </td>
                                <td class="c">
                                    <div style="display:flex;gap:5px;justify-content:center;">
                                        <a href="{{ route('invoices.edit', $inv) }}" class="btn btn-xs">Edit</a>
                                        <a href="{{ route('invoices.pdf', $inv) }}" class="btn btn-xs" target="_blank">PDF</a>
                                        @can('write-records')<a href="{{ route('invoices.activity', $inv) }}" class="btn btn-xs">Share / Email</a>@endcan
                                        <form method="POST" action="{{ route('invoices.destroy', $inv) }}"
                                              onsubmit="return confirm('Delete {{ $inv->number }}?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-xs btn-danger">Del</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:12px 16px;">
                {{ $invoices->links('partials.pagination') }}
            </div>
        @endif
    </div>
</div>
@endsection
