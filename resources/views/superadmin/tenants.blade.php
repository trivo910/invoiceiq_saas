@extends('superadmin.layout')
@section('content')
<h1>Workspaces</h1>
<form method="get" class="sa-panel sa-form">
    <label>Search name or slug<input name="q" maxlength="100" value="{{ request('q') }}"></label>
    <label>Status<select name="status"><option value="">All</option>@foreach(['active', 'suspended'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
    <button class="sa-button">Search</button>
</form>
<section class="sa-panel"><table class="sa-table"><thead><tr><th>Workspace</th><th>Status</th><th>Created</th></tr></thead><tbody>
@forelse($tenants as $tenant)<tr><td><a href="{{ route('superadmin.tenant', $tenant) }}">{{ $tenant->name }}</a><br><small>{{ $tenant->slug }}</small></td><td>{{ $tenant->status }}</td><td>{{ $tenant->created_at->format('d M Y') }}</td></tr>@empty<tr><td colspan="3">No workspaces found.</td></tr>@endforelse
</tbody></table>{{ $tenants->links() }}</section>
@endsection
