@extends('layouts.app')
@section('title','Items')
@section('content')
<div class="page">
    <div class="page-head flex justify-between items-center">
        <div><div class="page-title">Items &amp; Services</div><div class="page-subtitle">{{ $items->total() }} items in master</div></div>
        <div class="flex items-center" style="gap:8px;">
            @can('import-records') @include('imports.template-modal', ['entity' => 'items']) @endcan
            <button class="btn btn-primary" onclick="openM()">+ Add Item</button>
        </div>
    </div>
    <form method="GET" class="toolbar"><input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, HSN…" class="form-control" style="max-width:320px;"><button class="btn btn-sm">Search</button>@if(request('q'))<a href="{{ route('items.index') }}" class="btn btn-sm btn-ghost">Clear</a>@endif</form>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Item / Service</th><th>HSN/SAC</th><th>Default Rate</th><th>GST %</th><th>Unit</th><th>Type</th><th class="c">Actions</th></tr></thead><tbody>
    @forelse($items as $item)
    <tr><td><div style="font-weight:500;">{{ $item->name }}</div><div style="font-size:11px;color:var(--t4);">{{ $item->description }}</div></td>
    <td style="font-family:monospace;font-size:11px;">{{ $item->hsn_sac ?: '—' }}</td>
    <td style="font-weight:500;font-variant-numeric:tabular-nums;">{{ fmt_inr($item->rate) }}</td>
    <td><span class="badge badge-blue">{{ $item->gst_rate }}%</span></td>
    <td style="color:var(--t3);">{{ $item->unit }}</td>
    <td><span class="badge {{ $item->type==='goods'?'badge-green':'badge-gray' }}">{{ ucfirst($item->type) }}</span></td>
    <td class="c"><div style="display:flex;gap:5px;justify-content:center;"><button class="btn btn-xs" onclick='openE(@json($item))'>Edit</button><form method="POST" action="{{ route('items.destroy',$item) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-xs btn-danger">Del</button></form></div></td></tr>
    @empty<tr><td colspan="7" style="text-align:center;padding:50px;color:var(--t4);">No items yet.</td></tr>
    @endforelse
    </tbody></table></div><div style="padding:12px 16px;">{{ $items->links('partials.pagination') }}</div></div>
</div>
<div id="iModal" class="modal-overlay" style="display:none;"><div class="modal-box">
    <div class="modal-head"><div class="modal-title" id="mTitle">Add Item</div><button class="modal-close" onclick="closeM()">×</button></div>
    <form id="iForm" method="POST" action="{{ route('items.store') }}">@csrf<input type="hidden" id="fM" name="_method" value="">
    <div class="modal-body">
        <div class="form-group"><label class="form-label">Item / Service Name *</label><input type="text" name="name" id="fn" class="form-control" required></div>
        <div class="form-row form-row-2"><div class="form-group"><label class="form-label">Default Rate (₹) *</label><input type="number" name="rate" id="fr" class="form-control" min="0" step="0.01" required></div><div class="form-group"><label class="form-label">GST Rate *</label><select name="gst_rate" id="fg" class="form-control"><option value="0">0%</option><option value="5">5%</option><option value="12">12%</option><option value="18" selected>18%</option><option value="28">28%</option></select></div></div>
        <div class="form-row form-row-3"><div class="form-group"><label class="form-label">Unit</label><select name="unit" id="fu" class="form-control"><option>Nos</option><option>Pcs</option><option>Hrs</option><option>Kg</option><option>Ltr</option><option>Month</option><option>Year</option><option>Sqft</option></select></div><div class="form-group"><label class="form-label">HSN/SAC</label><input type="text" name="hsn_sac" id="fh" class="form-control"></div><div class="form-group"><label class="form-label">Type</label><select name="type" id="ft" class="form-control"><option value="service">Service</option><option value="goods">Goods</option></select></div></div>
        <div class="form-group"><label class="form-label">Description</label><input type="text" name="description" id="fd" class="form-control"></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn" onclick="closeM()">Cancel</button><button class="btn btn-primary">Save Item</button></div></form>
</div></div>
@push('scripts')<script>
function closeM(){document.getElementById('iModal').style.display='none';}
function openM(){document.getElementById('iModal').style.display='flex';document.getElementById('mTitle').textContent='Add Item';document.getElementById('iForm').action='{{ route('items.store') }}';document.getElementById('fM').value='';['fn','fr','fh','fd'].forEach(id=>{const e=document.getElementById(id);if(e)e.value='';});document.getElementById('fg').value='18';document.getElementById('fu').value='Nos';document.getElementById('ft').value='service';}
function openE(item){document.getElementById('iModal').style.display='flex';document.getElementById('mTitle').textContent='Edit Item';document.getElementById('iForm').action='{{ url('items') }}/'+item.id;document.getElementById('fM').value='PUT';document.getElementById('fn').value=item.name||'';document.getElementById('fr').value=item.rate||0;document.getElementById('fg').value=item.gst_rate||18;document.getElementById('fu').value=item.unit||'Nos';document.getElementById('fh').value=item.hsn_sac||'';document.getElementById('ft').value=item.type||'service';document.getElementById('fd').value=item.description||'';}
</script>@endpush
@endsection
