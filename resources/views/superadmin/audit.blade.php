@extends('superadmin.layout')
@section('content')
<h1>Platform audit trail</h1><p>Access and sensitive changes are recorded here. Records cannot be edited through the application.</p>
<section class="sa-panel"><table class="sa-table"><thead><tr><th>Time</th><th>Actor</th><th>Action / workspace</th><th>Reason / details</th></tr></thead><tbody>
@forelse($logs as $log)<tr><td>{{ $log->created_at->format('d M Y H:i:s') }}</td><td>{{ $log->super_admin_id ?? 'Unauthenticated' }}</td><td>{{ $log->action }}<br>Workspace {{ $log->tenant_id ?? 'N/A' }}</td><td><p>{{ $log->reason }}</p><pre class="sa-detail">{{ json_encode($log->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td></tr>@empty<tr><td colspan="4">No audit entries.</td></tr>@endforelse
</tbody></table>{{ $logs->links() }}</section>
@endsection
