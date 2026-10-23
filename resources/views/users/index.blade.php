@extends('layouts.app')
@section('title', 'Workspace members')
@section('content')
<div class="page">
    <div class="page-head"><div><div class="page-title">Workspace members</div><div class="page-subtitle">{{ $users->count() }} members · access applies to this workspace</div></div></div>
    @if(session('success'))<div class="flash flash-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="flash flash-error">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
    <div class="card" style="padding:20px;margin-bottom:18px"><h3>Invite a teammate</h3><form method="POST" action="{{ route('users.store') }}" class="form-row form-row-2">@csrf<div class="form-group"><label class="form-label">Email address</label><input class="form-control" type="email" name="email" required></div><div class="form-group"><label class="form-label">Role</label><select class="form-control" name="role"><option value="accountant">Accountant</option><option value="staff">Staff</option><option value="viewer">Viewer</option><option value="admin">Admin</option></select></div><button class="btn btn-primary" style="align-self:end">Send invitation</button></form></div>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Member since</th><th>Action</th></tr></thead><tbody>
    @foreach($users as $member)<tr><td>{{ $member->name }} @if($member->id === auth()->id())<span class="badge badge-gray">You</span>@endif</td><td>{{ $member->email }}</td><td>@if($member->pivot->role === 'owner')<span class="badge badge-blue">Owner</span>@else<form method="POST" action="{{ route('users.role', $member->id) }}">@csrf @method('PATCH')<select name="role" onchange="this.form.submit()">@foreach(['admin'=>'Admin','accountant'=>'Accountant','staff'=>'Staff','viewer'=>'Viewer'] as $role=>$label)<option value="{{ $role }}" @selected($member->pivot->role === $role)>{{ $label }}</option>@endforeach</select></form>@endif</td><td>{{ $member->pivot->created_at?->format('d M Y') }}</td><td>@if($member->id !== auth()->id() && $member->pivot->role !== 'owner')<form method="POST" action="{{ route('users.destroy', $member->id) }}" onsubmit="return confirm('Remove this member from the workspace?')">@csrf @method('DELETE')<button class="btn btn-xs btn-danger">Remove</button></form>@endif</td></tr>@endforeach
    </tbody></table></div></div>
    @if($invitations->isNotEmpty())<div class="card" style="padding:20px;margin-top:18px"><h3>Pending invitations</h3>@foreach($invitations as $invite)<p>{{ $invite->email }} · {{ ucfirst($invite->role) }} · expires {{ $invite->expires_at->format('d M Y') }}</p>@endforeach</div>@endif
</div>
@endsection
