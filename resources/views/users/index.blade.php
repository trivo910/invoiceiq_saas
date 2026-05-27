@extends('layouts.app')
@section('title', 'Users')
@section('content')
<div class="page">

    {{-- Header --}}
    <div class="page-head flex justify-between items-center">
        <div>
            <div class="page-title">Users</div>
            <div class="page-subtitle">{{ $stats['total'] }} {{ $stats['total'] == 1 ? 'user' : 'users' }} · Manage access and roles</div>
        </div>
        <button class="btn btn-primary" onclick="openM()">+ Add User</button>
    </div>

    {{-- Stat cards --}}
    <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px;">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value">{{ $stats['total'] }}</div>
        </div>
        <div class="stat-card acc">
            <div class="stat-label">Admins</div>
            <div class="stat-value">{{ $stats['admin'] }}</div>
        </div>
        <div class="stat-card ok">
            <div class="stat-label">Staff</div>
            <div class="stat-value">{{ $stats['staff'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Viewers</div>
            <div class="stat-value">{{ $stats['viewer'] }}</div>
        </div>
    </div>

    {{-- Users table --}}
    <div class="card">
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Member Since</th>
                        <th class="c">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $u)
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:32px;height:32px;border-radius:50%;background:var(--accent-l);color:var(--accent-t);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">
                                    {{ strtoupper(substr($u->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight:600;color:var(--t1);">
                                        {{ $u->name }}
                                        @if($u->id === auth()->id())
                                            <span class="badge badge-gray" style="margin-left:4px;font-size:9px;">You</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="color:var(--t3);">{{ $u->email }}</td>
                        <td><span class="badge {{ $u->role_color }}">{{ $u->role_label }}</span></td>
                        <td style="color:var(--t4);font-size:11px;">{{ $u->created_at->format('d M Y') }}</td>
                        <td class="c">
                            <div style="display:flex;gap:6px;justify-content:center;">
                                <button class="btn btn-xs" onclick='openE(@json($u))'>Edit</button>
                                @if($u->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $u) }}"
                                          onsubmit="return confirm('Delete {{ addslashes($u->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-danger">Del</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;padding:60px;color:var(--t4);">
                            <div style="font-size:28px;margin-bottom:10px;">👥</div>
                            <div style="font-size:14px;font-weight:500;color:var(--t3);margin-bottom:4px;">No users yet</div>
                            <div style="font-size:12px;">Click <strong>+ Add User</strong> to create the first user.</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Role reference --}}
    <div class="card" style="margin-top:18px;padding:16px 20px;">
        <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--t4);margin-bottom:12px;">Role Permissions</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span class="badge badge-blue">Admin</span>
                </div>
                <div style="font-size:11px;color:var(--t3);line-height:1.7;">Full access · Manage users · Manage settings · All CRUD operations</div>
            </div>
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span class="badge badge-green">Staff</span>
                </div>
                <div style="font-size:11px;color:var(--t3);line-height:1.7;">Create/edit invoices, customers, items · View reports · No settings or user management</div>
            </div>
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span class="badge badge-gray">Viewer</span>
                </div>
                <div style="font-size:11px;color:var(--t3);line-height:1.7;">Read-only · View invoices, customers, items, reports · Cannot create or edit anything</div>
            </div>
        </div>
    </div>

</div>

{{-- Add / Edit Modal --}}
<div id="uModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title" id="mTitle">Add User</div>
            <button class="modal-close" onclick="closeM()">×</button>
        </div>
        <form id="uForm" method="POST" action="{{ route('users.store') }}">
            @csrf
            <input type="hidden" id="fMethod" name="_method" value="">
            <div class="modal-body">

                <div class="form-group">
                    <label class="form-label">Full Name <span class="req">*</span></label>
                    <input type="text" name="name" id="fName" class="form-control" required placeholder="Jane Smith">
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address <span class="req">*</span></label>
                    <input type="email" name="email" id="fEmail" class="form-control" required placeholder="jane@company.com">
                </div>

                <div class="form-group">
                    <label class="form-label">Role <span class="req">*</span></label>
                    <select name="role" id="fRole" class="form-control">
                        <option value="staff">Staff — Create/edit invoices, customers, items</option>
                        <option value="admin">Admin — Full access including user management</option>
                        <option value="viewer">Viewer — Read-only access</option>
                    </select>
                </div>

                <div id="pwdHint" style="display:none;margin-bottom:8px;">
                    <div style="font-size:11px;color:var(--t4);padding:8px 10px;background:var(--s2);border-radius:var(--radius);border:1px solid var(--bdr);">
                        Leave password fields blank to keep the current password.
                    </div>
                </div>

                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label class="form-label" id="pwdLabel">Password <span class="req">*</span></label>
                        <input type="password" name="password" id="fPwd" class="form-control" placeholder="Min. 8 characters">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="fPwdC" class="form-control" placeholder="Repeat password">
                    </div>
                </div>

            </div>
            <div class="modal-foot">
                <button type="button" class="btn" onclick="closeM()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="mSubmit">Create User</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function closeM() {
    document.getElementById('uModal').style.display = 'none';
}

function openM() {
    document.getElementById('uModal').style.display = 'flex';
    document.getElementById('mTitle').textContent = 'Add User';
    document.getElementById('mSubmit').textContent = 'Create User';
    document.getElementById('uForm').action = '{{ route('users.store') }}';
    document.getElementById('fMethod').value = '';
    document.getElementById('pwdHint').style.display = 'none';
    document.getElementById('pwdLabel').innerHTML = 'Password <span class="req">*</span>';
    document.getElementById('fPwd').required = true;
    ['fName','fEmail','fPwd','fPwdC'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('fRole').value = 'staff';
}

function openE(u) {
    document.getElementById('uModal').style.display = 'flex';
    document.getElementById('mTitle').textContent = 'Edit User';
    document.getElementById('mSubmit').textContent = 'Save Changes';
    document.getElementById('uForm').action = '{{ url('users') }}/' + u.id;
    document.getElementById('fMethod').value = 'PUT';
    document.getElementById('pwdHint').style.display = 'block';
    document.getElementById('pwdLabel').textContent = 'New Password';
    document.getElementById('fPwd').required = false;
    document.getElementById('fName').value  = u.name  || '';
    document.getElementById('fEmail').value = u.email || '';
    document.getElementById('fRole').value  = u.role  || 'staff';
    document.getElementById('fPwd').value   = '';
    document.getElementById('fPwdC').value  = '';
}


</script>
@endpush
@endsection
