@extends('layouts.app')
@section('title','Customers')
@section('content')
<div class="page">

    {{-- Header --}}
    <div class="page-head flex justify-between items-center">
        <div>
            <div class="page-title">Customers</div>
            <div class="page-subtitle">{{ $stats['total'] }} customers &middot; {{ $stats['states'] }} {{ $stats['states'] == 1 ? 'state' : 'states' }} covered</div>
        </div>
        <button class="btn btn-primary" onclick="openM()">+ Add Customer</button>
    </div>

    {{-- Stat cards --}}
    <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px;">
        <div class="stat-card">
            <div class="stat-label">Total Customers</div>
            <div class="stat-value">{{ $stats['total'] }}</div>
        </div>
        <div class="stat-card acc">
            <div class="stat-label">GST Registered</div>
            <div class="stat-value">{{ $stats['with_gstin'] }}</div>
        </div>
        <div class="stat-card ok">
            <div class="stat-label">With Email</div>
            <div class="stat-value">{{ $stats['with_email'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">States Covered</div>
            <div class="stat-value">{{ $stats['states'] }}</div>
        </div>
    </div>

    {{-- Search toolbar --}}
    <form method="GET" class="toolbar">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, GSTIN…" class="form-control" style="max-width:340px;">
        <button class="btn btn-sm">Search</button>
        @if(request('q'))
            <a href="{{ route('customers.index') }}" class="btn btn-sm btn-ghost">Clear</a>
            <span style="font-size:12px;color:var(--t4);">{{ $customers->total() }} result{{ $customers->total() != 1 ? 's' : '' }} for "{{ request('q') }}"</span>
        @endif
    </form>

    {{-- Table card --}}
    <div class="card">
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>GSTIN</th>
                        <th>City / State</th>
                        <th>PIN</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Billing Address</th>
                        <th style="text-align:center;">Terms</th>
                        <th class="c">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($customers as $c)
                    <tr>
                        <td style="font-weight:600;color:var(--t1);">{{ $c->name }}</td>
                        <td>
                            @if($c->gstin)
                                <span class="badge badge-blue" style="font-family:monospace;letter-spacing:.3px;">{{ $c->gstin }}</span>
                            @else
                                <span style="color:var(--t4);">—</span>
                            @endif
                        </td>
                        <td style="color:var(--t2);">
                            @if($c->billing_city || $c->billing_state)
                                {{ $c->billing_city }}{{ $c->billing_city && $c->billing_state ? ', ' : '' }}{{ $c->billing_state }}
                            @else
                                <span style="color:var(--t4);">—</span>
                            @endif
                        </td>
                        <td style="color:var(--t4);font-size:11px;font-family:monospace;">{{ $c->billing_pincode ?: '—' }}</td>
                        <td style="color:var(--t3);">{{ $c->phone ?: '—' }}</td>
                        <td style="color:var(--t3);">{{ $c->email ?: '—' }}</td>
                        <td style="color:var(--t4);font-size:11px;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $c->billing_address }}">{{ $c->billing_address ?: '—' }}</td>
                        <td style="text-align:center;">
                            <span class="badge badge-gray">{{ $c->payment_terms }}d</span>
                        </td>
                        <td class="c">
                            <div style="display:flex;gap:6px;justify-content:center;">
                                <button class="btn btn-xs" onclick='openE(@json($c))'>Edit</button>
                                <form method="POST" action="{{ route('customers.destroy',$c) }}" onsubmit="return confirm('Delete customer?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger">Del</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:60px;color:var(--t4);">
                            <div style="font-size:32px;margin-bottom:10px;">👤</div>
                            <div style="font-size:14px;font-weight:500;color:var(--t3);margin-bottom:6px;">No customers yet</div>
                            <div style="font-size:12px;">Click <strong>+ Add Customer</strong> to get started.</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($customers->hasPages())
        <div style="padding:12px 16px;border-top:1px solid var(--bdr);">
            {{ $customers->links('partials.pagination') }}
        </div>
        @endif
    </div>

</div>

{{-- Add / Edit Modal --}}
<div id="cModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title" id="mTitle">Add Customer</div>
            <button class="modal-close" onclick="closeM()">×</button>
        </div>
        <form id="cForm" method="POST" action="{{ route('customers.store') }}" onsubmit="return validateCForm()">
            @csrf
            <input type="hidden" id="fM" name="_method" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name <span class="req">*</span></label>
                    <input type="text" name="name" id="fn" class="form-control" placeholder="Customer / company name">
                    <div class="error-msg" id="err-fn"></div>
                </div>
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label class="form-label">GSTIN</label>
                        <input name="gstin" id="fg" class="form-control" placeholder="27AABCT1234R1ZX" style="font-family:monospace;" maxlength="15" oninput="autoStateFromGstin(this.value)">
                        <div class="error-msg" id="err-fg"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input name="phone" id="fp" class="form-control" placeholder="98XXXXXXXX" maxlength="15">
                        <div class="error-msg" id="err-fp"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="text" name="email" id="fe" class="form-control" placeholder="billing@company.com">
                    <div class="error-msg" id="err-fe"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Billing Address</label>
                    <input name="billing_address" id="fa" class="form-control" placeholder="Street / area">
                </div>
                <div class="form-row form-row-3">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input name="billing_city" id="fc" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <select name="billing_state" id="fs" class="form-control" onchange="setStateCode(this.value)">
                            <option value="">— Select State —</option>
                            <option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
                            <option value="Andhra Pradesh">Andhra Pradesh</option>
                            <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                            <option value="Assam">Assam</option>
                            <option value="Bihar">Bihar</option>
                            <option value="Chandigarh">Chandigarh</option>
                            <option value="Chhattisgarh">Chhattisgarh</option>
                            <option value="Dadra and Nagar Haveli and Daman and Diu">Dadra and Nagar Haveli and Daman and Diu</option>
                            <option value="Delhi">Delhi</option>
                            <option value="Goa">Goa</option>
                            <option value="Gujarat">Gujarat</option>
                            <option value="Haryana">Haryana</option>
                            <option value="Himachal Pradesh">Himachal Pradesh</option>
                            <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                            <option value="Jharkhand">Jharkhand</option>
                            <option value="Karnataka">Karnataka</option>
                            <option value="Kerala">Kerala</option>
                            <option value="Ladakh">Ladakh</option>
                            <option value="Lakshadweep">Lakshadweep</option>
                            <option value="Madhya Pradesh">Madhya Pradesh</option>
                            <option value="Maharashtra">Maharashtra</option>
                            <option value="Manipur">Manipur</option>
                            <option value="Meghalaya">Meghalaya</option>
                            <option value="Mizoram">Mizoram</option>
                            <option value="Nagaland">Nagaland</option>
                            <option value="Odisha">Odisha</option>
                            <option value="Puducherry">Puducherry</option>
                            <option value="Punjab">Punjab</option>
                            <option value="Rajasthan">Rajasthan</option>
                            <option value="Sikkim">Sikkim</option>
                            <option value="Tamil Nadu">Tamil Nadu</option>
                            <option value="Telangana">Telangana</option>
                            <option value="Tripura">Tripura</option>
                            <option value="Uttar Pradesh">Uttar Pradesh</option>
                            <option value="Uttarakhand">Uttarakhand</option>
                            <option value="West Bengal">West Bengal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">State Code</label>
                        <input name="billing_state_code" id="fsc" class="form-control" placeholder="27">
                    </div>
                </div>
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label class="form-label">PIN Code</label>
                        <input name="billing_pincode" id="fpin" class="form-control" placeholder="400001">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Terms (days)</label>
                        <input type="number" name="payment_terms" id="ft" class="form-control" value="30" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" onclick="closeM()">Cancel</button>
                <button class="btn btn-primary">Save Customer</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
const STATE_CODES = {
    'Andaman and Nicobar Islands': '35',
    'Andhra Pradesh': '37',
    'Arunachal Pradesh': '12',
    'Assam': '18',
    'Bihar': '10',
    'Chandigarh': '04',
    'Chhattisgarh': '22',
    'Dadra and Nagar Haveli and Daman and Diu': '26',
    'Delhi': '07',
    'Goa': '30',
    'Gujarat': '24',
    'Haryana': '06',
    'Himachal Pradesh': '02',
    'Jammu and Kashmir': '01',
    'Jharkhand': '20',
    'Karnataka': '29',
    'Kerala': '32',
    'Ladakh': '38',
    'Lakshadweep': '31',
    'Madhya Pradesh': '23',
    'Maharashtra': '27',
    'Manipur': '14',
    'Meghalaya': '17',
    'Mizoram': '15',
    'Nagaland': '13',
    'Odisha': '21',
    'Puducherry': '34',
    'Punjab': '03',
    'Rajasthan': '08',
    'Sikkim': '11',
    'Tamil Nadu': '33',
    'Telangana': '36',
    'Tripura': '16',
    'Uttar Pradesh': '09',
    'Uttarakhand': '05',
    'West Bengal': '19',
};

function setStateCode(state) {
    document.getElementById('fsc').value = STATE_CODES[state] || '';
}

function autoStateFromGstin(gstin) {
    if (gstin.length < 2) return;
    const code = gstin.slice(0, 2);
    const name = Object.keys(STATE_CODES).find(k => STATE_CODES[k] === code);
    if (name) {
        document.getElementById('fs').value  = name;
        document.getElementById('fsc').value = code;
    }
}

function clearCErrors() {
    ['err-fn','err-fg','err-fp','err-fe'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '';
    });
    ['fn','fg','fp','fe'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.borderColor = '';
    });
}

function setError(fieldId, errId, msg) {
    document.getElementById(errId).textContent = msg;
    document.getElementById(fieldId).style.borderColor = 'var(--err)';
}

function validateCForm() {
    clearCErrors();
    let valid = true;

    const name  = document.getElementById('fn').value.trim();
    const gstin = document.getElementById('fg').value.trim().toUpperCase();
    const phone = document.getElementById('fp').value.trim();
    const email = document.getElementById('fe').value.trim();

    if (!name) {
        setError('fn', 'err-fn', 'Customer name is required.');
        valid = false;
    }

    if (gstin && !/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/.test(gstin)) {
        setError('fg', 'err-fg', 'Invalid GSTIN format (e.g. 27AABCT1234R1ZX).');
        valid = false;
    }

    if (phone && !/^[0-9+\-\s]{7,15}$/.test(phone)) {
        setError('fp', 'err-fp', 'Enter a valid phone number.');
        valid = false;
    }

    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        setError('fe', 'err-fe', 'Enter a valid email address.');
        valid = false;
    }

    if (!valid) {
        const first = document.querySelector('#cModal .error-msg:not(:empty)');
        if (first) first.previousElementSibling.focus();
    }

    return valid;
}

function closeM() { document.getElementById('cModal').style.display = 'none'; }
function openM() {
    clearCErrors();
    document.getElementById('cModal').style.display = 'flex';
    document.getElementById('mTitle').textContent = 'Add Customer';
    document.getElementById('cForm').action = '{{ route('customers.store') }}';
    document.getElementById('fM').value = '';
    ['fn','fg','fp','fe','fa','fc','fsc','fpin'].forEach(id => {
        const e = document.getElementById(id);
        if (e) e.value = '';
    });
    document.getElementById('fs').value = '';
    document.getElementById('ft').value = '30';
}
function openE(c) {
    clearCErrors();
    document.getElementById('cModal').style.display = 'flex';
    document.getElementById('mTitle').textContent = 'Edit Customer';
    document.getElementById('cForm').action = '{{ url('customers') }}/' + c.id;
    document.getElementById('fM').value = 'PUT';
    const m = { name:'fn', gstin:'fg', phone:'fp', email:'fe', billing_address:'fa', billing_city:'fc', billing_state_code:'fsc', billing_pincode:'fpin', payment_terms:'ft' };
    Object.entries(m).forEach(([k, id]) => {
        const e = document.getElementById(id);
        if (e) e.value = c[k] || '';
    });
    document.getElementById('fs').value = c.billing_state || '';
}

</script>
@endpush
@endsection
