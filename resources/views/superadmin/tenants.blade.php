@extends('superadmin.layout')
@section('content')
<h1>Workspaces</h1>
<form method="get" class="sa-panel sa-form">
    <label>Search name or slug<input name="q" maxlength="100" value="{{ request('q') }}"></label>
    <label>Status<select name="status"><option value="">All active workspaces</option>@foreach(['active', 'suspended', 'deleted'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
    <button class="sa-button">Search</button>
</form>
<section class="sa-panel"><table class="sa-table"><thead><tr><th>Workspace</th><th>Status</th><th>Created</th></tr></thead><tbody>
@forelse($tenants as $tenant)<tr><td>@if(!$tenant->trashed())<a href="{{ route('superadmin.tenant', $tenant) }}">{{ $tenant->name }}</a>@else{{ $tenant->name }}@endif<br><small>{{ $tenant->slug }}</small></td><td>{{ $tenant->trashed() ? 'deleted' : $tenant->status }}</td><td>{{ $tenant->created_at->format('d M Y') }}</td><td>@if($tenant->trashed())<form method="post" action="{{ route('superadmin.tenant.restore', $tenant->id) }}">@csrf<label>Reason <input name="reason" minlength="5" maxlength="1000" required></label><button class="sa-button">Restore</button></form>@endif</td></tr>@empty<tr><td colspan="4">No workspaces found.</td></tr>@endforelse
</tbody></table>{{ $tenants->links() }}</section>
@endsection
