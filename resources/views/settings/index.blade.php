@extends('layouts.app')
@section('title','Settings')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<style>
#emailBodyEditor .ql-editor { min-height: 180px; font-size: 13px; font-family: var(--font); line-height: 1.6; }
#emailBodyEditor .ql-toolbar.ql-snow { border-radius: var(--radius) var(--radius) 0 0; border-color: var(--bdr2); background: var(--s2); }
#emailBodyEditor .ql-container.ql-snow { border-color: var(--bdr2); border-radius: 0 0 var(--radius) var(--radius); }
#emailBodyEditor .ql-toolbar.ql-snow .ql-picker-label,
#emailBodyEditor .ql-toolbar.ql-snow button { color: var(--t2); }
#emailBodyEditor .ql-toolbar.ql-snow button:hover,
#emailBodyEditor .ql-toolbar.ql-snow .ql-picker-label:hover { color: var(--accent); }
#emailBodyEditor .ql-toolbar.ql-snow button.ql-active { color: var(--accent); }
</style>
@endpush

@section('content')
<div class="page" style="max-width:760px;">
    <div class="page-head"><div class="page-title">Settings</div><div class="page-subtitle">Company profile &amp; billing preferences</div></div>

    {{-- Logo card (separate form, multipart) --}}
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><div class="card-title">Company Logo</div></div>
        <div class="card-body">
            @if(!empty($settings['company_logo']))
                <div style="margin-bottom:16px;">
                    <img src="{{ route('settings.logo.image') }}"
                         alt="Company Logo"
                         style="max-height:80px;max-width:240px;object-fit:contain;display:block;border:1px solid var(--bdr);border-radius:var(--radius);padding:8px;background:var(--s2);">
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <form method="POST" action="{{ route('settings.logo.remove') }}" onsubmit="return confirm('Remove the logo?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Remove Logo</button>
                    </form>
                    <span style="font-size:13px;color:var(--t4);">Upload a new file below to replace it</span>
                </div>
            @else
                <p style="font-size:14px;color:var(--t3);margin-bottom:14px;">No logo uploaded yet. The company name will appear as text on PDFs.</p>
            @endif

            <form method="POST" action="{{ route('settings.logo.upload') }}" enctype="multipart/form-data" style="margin-top:16px;">
                @csrf
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Upload Logo <span style="font-size:11px;color:var(--t4);text-transform:none;letter-spacing:0;">(JPEG or PNG, max 2 MB)</span></label>
                    <input type="file" name="company_logo" accept="image/jpeg,image/png,image/gif" class="form-control" style="padding:8px 11px;">
                    @error('company_logo')<div class="error-msg">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:12px;">Upload Logo</button>
            </form>
        </div>
    </div>

    {{-- Main settings form --}}
    <section class="card" style="margin-bottom:16px">
        <div class="card-header"><div class="card-title">Invoice branding</div></div>
        <form method="post" action="{{ route('settings.branding') }}" class="card-body">@csrf
            <p>Advanced template and accent settings follow your plan's custom branding entitlement. Your existing company profile and private logo remain available.</p>
            <label class="form-label" for="invoicePdfTemplate">PDF template</label>
            <select id="invoicePdfTemplate" name="invoice_pdf_template" class="form-control"><option value="classic" @selected(($settings['invoice_pdf_template'] ?? 'classic') === 'classic')>Classic</option><option value="accent" @selected(($settings['invoice_pdf_template'] ?? '') === 'accent')>Accent</option></select>
            <label class="form-label" for="brandColor">Accent color</label><input type="color" id="brandColor" name="brand_color" value="{{ preg_match('/^#[0-9A-Fa-f]{6}$/D', $settings['brand_color'] ?? '') ? $settings['brand_color'] : '#245dc9' }}">
            <button class="btn btn-primary btn-sm" type="submit">Save branding</button>
        </form>
    </section>

    <form id="mainSettingsForm" method="POST" action="{{ route('settings.update') }}">@csrf
        <div class="card" style="margin-bottom:16px;"><div class="card-header"><div class="card-title">Company Details</div></div><div class="card-body"><div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
            @foreach([['company_name','Company Name','text'],['company_gstin','GSTIN','text'],['company_pan','PAN','text'],['company_address','Address','text'],['company_city','City','text'],['company_state','State','text'],['company_state_code','State Code','text'],['company_phone','Phone','tel'],['company_email','Email','email']] as [$k,$l,$t])
            <div class="form-group"><label class="form-label">{{ $l }}</label><input type="{{ $t }}" name="{{ $k }}" class="form-control" value="{{ old($k,$settings[$k]??'') }}"></div>
            @endforeach
        </div></div></div>
        <div class="card" style="margin-bottom:16px;"><div class="card-header"><div class="card-title">Bank Details</div></div><div class="card-body"><div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
            @foreach([['bank_name','Bank Name'],['bank_acc_no','Account Number'],['bank_ifsc','IFSC Code'],['bank_branch','Branch']] as [$k,$l])
            <div class="form-group"><label class="form-label">{{ $l }}</label><input type="text" name="{{ $k }}" class="form-control" value="{{ old($k,$settings[$k]??'') }}"></div>
            @endforeach
        </div></div></div>
        <div class="card" style="margin-bottom:16px;"><div class="card-header"><div class="card-title">Invoice Settings</div></div><div class="card-body"><div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 16px;">
            @foreach([['invoice_prefix','Invoice Prefix','text'],['invoice_seq','Next Sequence No.','number'],['default_terms','Payment Terms (days)','number']] as [$k,$l,$t])
            <div class="form-group"><label class="form-label">{{ $l }}</label><input type="{{ $t }}" name="{{ $k }}" class="form-control" value="{{ old($k,$settings[$k]??'') }}"></div>
            @endforeach
        </div></div></div>
        <div class="card" style="margin-bottom:16px;"><div class="card-header"><div class="card-title">Default Invoice Notes</div></div><div class="card-body">
            <textarea name="default_notes" class="form-control" rows="3">{{ old('default_notes',$settings['default_notes']??'') }}</textarea>
        </div></div>

        {{-- Email Settings --}}
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <div class="card-title">Email Settings</div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--t2);font-weight:500;">
                    <span>Send email on Finalise</span>
                    <span id="emailToggleWrap" style="position:relative;display:inline-block;width:40px;height:22px;">
                        <input type="checkbox" name="email_enabled" id="emailEnabled" value="1"
                               {{ ($settings['email_enabled']??'0')==='1' ? 'checked' : '' }}
                               onchange="toggleSmtp(this.checked)"
                               style="opacity:0;width:0;height:0;position:absolute;">
                        <span id="emailToggleSlider" style="position:absolute;inset:0;border-radius:11px;cursor:pointer;transition:background .2s;
                              background:{{ ($settings['email_enabled']??'0')==='1' ? 'var(--accent)' : 'var(--bdr2)' }};"></span>
                        <span id="emailToggleKnob" style="position:absolute;top:3px;width:16px;height:16px;border-radius:50%;background:#fff;transition:left .2s;
                              left:{{ ($settings['email_enabled']??'0')==='1' ? '21px' : '3px' }};"></span>
                    </span>
                </label>
            </div>
            <div class="card-body" id="smtpFields" style="{{ ($settings['email_enabled']??'0')!=='1' ? 'display:none;' : '' }}">
                <p style="font-size:12px;color:var(--t4);margin-bottom:14px;">Configure SMTP to send invoice emails directly from your mail server. Leave password blank to keep the existing one.</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
                    <div class="form-group">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com"
                               value="{{ old('smtp_host',$settings['smtp_host']??'') }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" placeholder="587"
                               value="{{ old('smtp_port',$settings['smtp_port']??'587') }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username / Email</label>
                        <input type="text" name="smtp_username" class="form-control" placeholder="you@example.com"
                               value="{{ old('smtp_username',$settings['smtp_username']??'') }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span style="font-size:11px;color:var(--t4);font-weight:400;">(leave blank to keep current)</span></label>
                        <input type="password" name="smtp_password" class="form-control" autocomplete="new-password" placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Encryption</label>
                        <select name="smtp_encryption" class="form-control">
                            @foreach(['tls'=>'TLS (STARTTLS)','ssl'=>'SSL','none'=>'None'] as $v=>$l)
                                <option value="{{ $v }}" {{ old('smtp_encryption',$settings['smtp_encryption']??'tls')===$v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">From Name</label>
                        <input type="text" name="smtp_from_name" class="form-control" placeholder="{{ $settings['company_name']??'Your Company' }}"
                               value="{{ old('smtp_from_name',$settings['smtp_from_name']??'') }}">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">From Email Address</label>
                        <input type="email" name="smtp_from_email" class="form-control" placeholder="invoices@yourcompany.com"
                               value="{{ old('smtp_from_email',$settings['smtp_from_email']??'') }}">
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;margin-top:4px;">
                    <button type="button" id="testEmailBtn" onclick="sendTestEmail()"
                            style="padding:7px 16px;font-size:13px;border-radius:var(--radius);border:1px solid var(--bdr2);background:var(--s2);color:var(--t2);cursor:pointer;font-weight:500;transition:background .15s;"
                            onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
                        Send Test Email
                    </button>
                    <span id="testEmailResult" style="font-size:13px;"></span>
                </div>
            </div>
        </div>

        {{-- Email Template --}}
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header"><div class="card-title">Email Template</div></div>
            <div class="card-body">
                <p style="font-size:12px;color:var(--t4);margin-bottom:14px;">
                    Customize the default email. Placeholders:
                    <code style="background:var(--s2);padding:1px 5px;border-radius:3px;">{invoice_number}</code>
                    <code style="background:var(--s2);padding:1px 5px;border-radius:3px;">{customer_name}</code>
                    <code style="background:var(--s2);padding:1px 5px;border-radius:3px;">{company_name}</code>
                    <code style="background:var(--s2);padding:1px 5px;border-radius:3px;">{amount}</code>
                    <code style="background:var(--s2);padding:1px 5px;border-radius:3px;">{due_date}</code>
                </p>
                <div class="form-group">
                    <label class="form-label">Default Subject</label>
                    <input type="text" name="email_subject" class="form-control"
                           placeholder="Invoice {invoice_number} from {company_name}"
                           value="{{ old('email_subject',$settings['email_subject']??'Invoice {invoice_number} from {company_name}') }}">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Default Email Body</label>

                    {{-- Placeholder insert chips --}}
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                        @foreach(['{invoice_number}','{customer_name}','{company_name}','{amount}','{due_date}'] as $ph)
                        <button type="button" onclick="insertPlaceholder('{{ $ph }}')"
                                style="font-size:11px;padding:3px 9px;border-radius:20px;border:1px solid var(--accent);
                                       background:var(--accent-l);color:var(--accent-t);cursor:pointer;line-height:1.6;
                                       font-family:monospace;transition:opacity .15s;"
                                onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'">{{ $ph }}</button>
                        @endforeach
                    </div>

                    {{-- Hidden textarea that holds the raw HTML value submitted with the form --}}
                    <textarea id="emailBodyInput" name="email_body" style="display:none;">{{ old('email_body',$settings['email_body']??'<p>Dear {customer_name},</p><p>Please find attached <strong>Invoice {invoice_number}</strong> from <strong>{company_name}</strong>.</p><p><strong>Amount:</strong> {amount}<br><strong>Due Date:</strong> {due_date}</p><p>The invoice PDF is included as a compressed attachment.</p><p>Regards,<br>{company_name}</p>') }}</textarea>

                    {{-- Quill editor mount point --}}
                    <div id="emailBodyEditor" style="border-radius:0 0 var(--radius) var(--radius);"></div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="padding:10px 28px;">Save Settings</button>
    </form>

<script>
function toggleSmtp(on) {
    document.getElementById('smtpFields').style.display = on ? '' : 'none';
    document.getElementById('emailToggleSlider').style.background = on ? 'var(--accent)' : 'var(--bdr2)';
    document.getElementById('emailToggleKnob').style.left = on ? '21px' : '3px';
}
</script>

@push('scripts')
<script>
async function sendTestEmail() {
    const btn    = document.getElementById('testEmailBtn');
    const result = document.getElementById('testEmailResult');
    btn.disabled  = true;
    result.style.color = '#6b7280';
    result.textContent = 'Sending…';

    const payload = new FormData();
    payload.append('_token', document.querySelector('input[name="_token"]').value);
    ['smtp_host','smtp_port','smtp_username','smtp_password','smtp_encryption','smtp_from_email','smtp_from_name'].forEach(name => {
        const el = document.querySelector(`[name="${name}"]`);
        if (el) payload.append(name, el.value);
    });

    try {
        const res  = await fetch('{{ route("settings.test-email") }}', { method: 'POST', body: payload });
        const json = await res.json();
        result.style.color = json.ok ? '#16a34a' : '#dc2626';
        result.textContent  = (json.ok ? '✓ ' : '✗ ') + json.message;
    } catch {
        result.style.color = '#dc2626';
        result.textContent = '✗ Request failed.';
    }

    btn.disabled = false;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.js"></script>
<script>
(function () {
    const textarea = document.getElementById('emailBodyInput');

    const quill = new Quill('#emailBodyEditor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link'],
                ['clean'],
            ],
        },
    });

    // Seed the editor with the stored HTML
    quill.clipboard.dangerouslyPasteHTML(textarea.value || '');

    // Before the main settings form submits, push Quill HTML back to the hidden textarea
    document.getElementById('mainSettingsForm').addEventListener('formdata', function (e) {
        e.formData.set('email_body', quill.root.innerHTML);
    });

    // Fallback for browsers that don't fire the formdata event
    document.getElementById('mainSettingsForm').addEventListener('submit', function () {
        textarea.value = quill.root.innerHTML;
    }, true);

    // Insert a placeholder text at the current cursor position
    window.insertPlaceholder = function (text) {
        quill.focus();
        const range = quill.getSelection(true);
        quill.insertText(range ? range.index : quill.getLength() - 1, text, Quill.sources.USER);
    };
})();
</script>
@endpush
</div>
@endsection
