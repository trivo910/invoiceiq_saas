@extends('layouts.app')
@section('title', isset($invoice) ? 'Edit '.$invoice->number : 'New Invoice')

@section('content')
@php
    $isEdit  = isset($invoice);
    $s       = $settings ?? [];
    $rows    = $isEdit ? $invoice->items->toArray() : [];
    $visCol  = $isEdit
        ? ($invoice->visible_columns ?? ['desc'=>1,'hsn'=>1,'disc'=>1,'unit'=>1,'cess'=>0])
        : ['desc'=>1,'hsn'=>1,'disc'=>1,'unit'=>1,'cess'=>0];
    $terms   = (int)($s['default_terms'] ?? 30);
    $dueDate = $isEdit
        ? $invoice->due_date?->format('Y-m-d')
        : date('Y-m-d', strtotime("+{$terms} days"));
@endphp

@if($errors->any())
<div style="padding:0 24px 0 24px;padding-top:16px;">
    <div class="flash flash-error">
        <strong>Please fix the following errors:</strong>
        <ul style="margin:6px 0 0 16px;padding:0;">
            @foreach($errors->all() as $error)
                <li style="font-size:13px;">{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<div style="display:flex;gap:18px;padding:24px;align-items:flex-start;">

{{-- ═══ Invoice Paper ══════════════════════════════════════ --}}
<div style="flex:1;min-width:0;">
<form id="invoiceForm" method="POST"
      action="{{ $isEdit ? route('invoices.update',$invoice) : route('invoices.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <input type="hidden" name="visible_columns" id="visibleColsInput">
    {{-- Email send fields (populated by modal) --}}
    <input type="hidden" name="send_email"    id="sendEmailFlag"    value="0">
    <input type="hidden" name="to_email"      id="h_to_email">
    <input type="hidden" name="cc_email"      id="h_cc_email">
    <input type="hidden" name="email_subject" id="h_email_subject">
    <input type="hidden" name="email_body"    id="h_email_body">

    <div id="client-errors" style="display:none;margin-bottom:16px;">
        <div class="flash flash-error">
            <strong>Please fix the following errors:</strong>
            <ul id="client-errors-list" style="margin:6px 0 0 16px;padding:0;"></ul>
        </div>
    </div>

    <div class="inv-paper">

    {{-- Dark header --}}
    <div class="inv-band">
        <div>
            <div class="co-name">{{ $s['company_name'] ?? 'Your Company' }}</div>
            <div class="co-detail">
                {{ $s['company_address'] ?? '' }}, {{ $s['company_city'] ?? '' }},
                {{ $s['company_state'] ?? '' }}<br>
                @if(!empty($s['company_phone'])) Ph: {{ $s['company_phone'] }} @endif
                @if(!empty($s['company_email'])) &nbsp;·&nbsp; {{ $s['company_email'] }} @endif
            </div>
            <div class="co-gstin">
                GSTIN: {{ $s['company_gstin'] ?? '—' }}
                &nbsp;|&nbsp; PAN: {{ $s['company_pan'] ?? '—' }}
            </div>
        </div>
        <div class="inv-meta">
            <div class="inv-num-label">Invoice No.</div>
            <input type="text" name="number" id="invNumber" class="inv-num-input" required
                   value="{{ old('number', $isEdit ? $invoice->number : $number) }}">
            <div style="margin-top:10px;">
                <select name="status" class="inv-status-sel">
                    @foreach(['draft'=>'Draft','sent'=>'Sent','paid'=>'Paid','partial'=>'Part Paid','cancelled'=>'Cancelled'] as $v=>$l)
                        <option value="{{ $v }}" {{ old('status', $isEdit ? $invoice->status : 'draft') === $v ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="inv-date-row">
                <div class="inv-date-block">
                    <div class="dl">Invoice Date</div>
                    <input type="date" name="invoice_date" class="inv-date-input" required
                           value="{{ old('invoice_date', $isEdit ? $invoice->invoice_date->format('Y-m-d') : date('Y-m-d')) }}">
                </div>
                <div class="inv-date-block">
                    <div class="dl">Due Date</div>
                    <input type="date" name="due_date" class="inv-date-input"
                           value="{{ old('due_date', $dueDate) }}">
                </div>
            </div>
        </div>
    </div>

    {{-- Bill To / Ship To --}}
    <div class="inv-parties">
        <div>
            <div class="party-label">Bill To</div>
            {{-- Hidden customer fields --}}
            <input type="hidden" name="customer_id"              id="f_cust_id"    value="{{ old('customer_id',    $isEdit ? $invoice->customer_id              : '') }}">
            <input type="hidden" name="customer_name"            id="f_cust_name"  value="{{ old('customer_name',  $isEdit ? $invoice->customer_name            : '') }}">
            <input type="hidden" name="customer_gstin"           id="f_cust_gstin" value="{{ old('customer_gstin', $isEdit ? $invoice->customer_gstin           : '') }}">
            <input type="hidden" name="customer_billing_address" id="f_cust_addr"  value="{{ old('customer_billing_address', $isEdit ? $invoice->customer_billing_address : '') }}">
            <input type="hidden" name="customer_city"            id="f_cust_city"  value="{{ old('customer_city',  $isEdit ? $invoice->customer_city            : '') }}">
            <input type="hidden" name="customer_state"           id="f_cust_state" value="{{ old('customer_state', $isEdit ? $invoice->customer_state           : '') }}">
            <input type="hidden" name="customer_state_code"      id="f_cust_sc"    value="{{ old('customer_state_code', $isEdit ? $invoice->customer_state_code : '') }}">
            <input type="hidden" name="is_intra_state"           id="f_intra"      value="{{ old('is_intra_state', $isEdit ? ($invoice->is_intra_state ? '1' : '0') : '1') }}">
            <input type="hidden" name="place_of_supply"          id="f_pos"        value="{{ old('place_of_supply', $isEdit ? $invoice->place_of_supply        : '') }}">
            <input type="hidden" name="place_of_supply_code"     id="f_pos_code"   value="{{ old('place_of_supply_code', $isEdit ? $invoice->place_of_supply_code : '') }}">
            <input type="hidden" id="f_cust_email" value="{{ $isEdit && $invoice->customer ? $invoice->customer->email : '' }}">

            {{-- Search box (shown when no customer) --}}
            <div id="custSearchWrap" style="{{ ($isEdit && $invoice->customer_name) ? 'display:none;' : '' }}position:relative;">
                <input type="text" id="custSearchInput" class="form-control" autocomplete="off"
                       placeholder="Search customer name or GSTIN…">
                <div id="custDd" class="cust-dd" style="display:none;"></div>
            </div>

            {{-- Filled state (shown when customer selected) --}}
            <div id="custFilled" style="{{ (!$isEdit || !$invoice->customer_name) ? 'display:none;' : '' }}">
                <div class="customer-filled">
                    <div class="cf-name">
                        <span id="cf_name">{{ $isEdit ? $invoice->customer_name : '' }}</span>
                        <button type="button" onclick="clearCustomer()"
                                style="background:none;border:none;cursor:pointer;font-size:11px;color:var(--t4);">× Change</button>
                    </div>
                    <div class="cf-detail" id="cf_detail">{{ $isEdit ? $invoice->customer_billing_address.', '.$invoice->customer_city : '' }}</div>
                    <div class="cf-gstin" id="cf_gstin">{{ $isEdit ? $invoice->customer_gstin : '' }}</div>
                </div>
            </div>

            <div class="supply-row">
                <span style="font-size:10px;color:var(--t4);">Place of supply:</span>
                <input type="text" id="posInput"
                       value="{{ $isEdit ? $invoice->place_of_supply : '' }}"
                       placeholder="State (code)"
                       style="font-size:11px;padding:3px 8px;border:1px solid var(--bdr);border-radius:5px;width:150px;outline:none;background:var(--s2);">
                <span class="supply-tag" id="gstTypeTag" style="display:none;"></span>
            </div>
        </div>
        <div>
            <div class="party-label">
                Ship To <small style="font-weight:400;text-transform:none;font-size:10px;letter-spacing:0;">(optional)</small>
            </div>
            <textarea class="form-control" name="shipping_address" rows="4"
                      placeholder="Same as billing address…">{{ $isEdit ? ($invoice->shipping_address ?? '') : '' }}</textarea>
        </div>
    </div>

    {{-- Line Items --}}
    <div class="inv-items">
        <div class="items-toolbar">
            <div class="it-title">Line Items</div>
            <div class="col-chips">
                <span>Columns:</span>
                @foreach(['desc'=>'Description','hsn'=>'HSN/SAC','disc'=>'Discount','unit'=>'Unit','cess'=>'Cess'] as $k=>$l)
                    <span class="col-chip {{ ($visCol[$k] ?? 0) ? 'on' : '' }}"
                          data-col="{{ $k }}" onclick="toggleColChip('{{ $k }}',this)">{{ $l }}</span>
                @endforeach
            </div>
        </div>
        <div class="table-wrap">
        <table class="items-tbl" id="itemsTable">
            <thead>
                <tr>
                    <th style="width:28px;">#</th>
                    <th style="min-width:160px;text-align:left;">Item / Service</th>
                    <th class="col-desc{{ ($visCol['desc']??0)?'':' hidden-col' }}" style="min-width:130px;text-align:left;">Description</th>
                    <th class="col-hsn{{ ($visCol['hsn']??0)?'':' hidden-col' }}" style="width:90px;text-align:left;">HSN/SAC</th>
                    <th class="r" style="width:60px;">Qty</th>
                    <th class="col-unit{{ ($visCol['unit']??0)?'':' hidden-col' }}" style="width:65px;">Unit</th>
                    <th class="r" style="width:100px;">Rate (₹)</th>
                    <th class="col-disc{{ ($visCol['disc']??0)?'':' hidden-col' }} r" style="width:72px;">Disc%</th>
                    <th class="r" style="width:95px;">Taxable</th>
                    <th class="c" style="width:60px;">GST%</th>
                    <th class="r" style="width:90px;">Tax (₹)</th>
                    <th class="r" style="width:95px;">Total (₹)</th>
                    <th style="width:28px;"></th>
                </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
        </table>
        </div>
        <button type="button" class="add-row-btn" onclick="addRow()">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <circle cx="7" cy="7" r="6.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M7 4v6M4 7h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Add line item
        </button>
    </div>

    {{-- Footer --}}
    <div class="inv-footer">
        <div>
            <div class="form-label">Notes / Terms</div>
            <textarea name="notes" class="form-control" rows="4"
            >{{ old('notes', $isEdit ? $invoice->notes : ($s['default_notes'] ?? '')) }}</textarea>
            <div style="margin-top:12px;padding:11px 13px;background:var(--s2);border-radius:var(--radius);border:1px solid var(--bdr);">
                <div class="form-label" style="margin-bottom:5px;">Bank Details</div>
                <div style="font-size:11px;color:var(--t2);line-height:1.9;">
                    {{ $s['bank_name'] ?? '' }} &nbsp;·&nbsp; A/C: {{ $s['bank_acc_no'] ?? '' }}<br>
                    IFSC: {{ $s['bank_ifsc'] ?? '' }} &nbsp;·&nbsp; {{ $s['bank_branch'] ?? '' }}
                </div>
            </div>
        </div>
        <div class="totals-block">
            <div class="total-row">
                <span class="tl">Subtotal</span>
                <span class="tv" id="t_subtotal">₹0.00</span>
            </div>
            <div class="total-row">
                <span class="tl">Line Discounts</span>
                <span class="tv" id="t_linedisc" style="color:var(--ok);">-₹0.00</span>
            </div>
            <div class="total-row" style="align-items:flex-start;">
                <span class="tl">Invoice Discount
                    <span class="disc-inline">
                        <input type="number" name="discount_value" id="discVal" min="0" step="0.01"
                               value="{{ old('discount_value', $isEdit ? $invoice->discount_value : 0) }}"
                               oninput="recalc()">
                        <select name="discount_type" id="discType" onchange="recalc()">
                            <option value="percent" {{ old('discount_type', $isEdit ? $invoice->discount_type : 'percent') === 'percent' ? 'selected' : '' }}>%</option>
                            <option value="flat"    {{ old('discount_type', $isEdit ? $invoice->discount_type : 'percent') === 'flat'    ? 'selected' : '' }}>₹</option>
                        </select>
                    </span>
                </span>
                <span class="tv" id="t_invdisc" style="color:var(--ok);">-₹0.00</span>
            </div>
            <div class="gst-breakdown">
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.7px;color:var(--t4);margin-bottom:6px;">GST Breakdown</div>
                <div id="gstRows"></div>
                <div class="gst-row total">
                    <span class="gl">Total Tax</span>
                    <span class="gv" id="t_totaltax">₹0.00</span>
                </div>
            </div>
            <div class="total-row">
                <span class="tl" style="font-size:10px;color:var(--t4);">Round Off</span>
                <span class="tv" id="t_roundoff" style="font-size:11px;color:var(--t4);">+₹0.00</span>
            </div>
            <div class="total-row separator grand">
                <span class="tl">Grand Total</span>
                <span class="tv" id="t_grand">₹0.00</span>
            </div>
            <div class="amount-words" id="t_words">Zero Rupees Only</div>
        </div>
    </div>

    </div>{{-- /inv-paper --}}
</form>
</div>{{-- /left column --}}

{{-- ═══ Right Action Panel ═════════════════════════════════ --}}
<div class="inv-actions no-print">

    <div class="act-card">
        <div class="act-card-title">Summary</div>
        <div class="summary-row"><span class="sl">Items</span><span class="sv" id="s_items">0</span></div>
        <div class="summary-row"><span class="sl">Subtotal</span><span class="sv" id="s_sub">₹0</span></div>
        <div class="summary-row"><span class="sl">Tax</span><span class="sv" id="s_tax">₹0</span></div>
        <div class="summary-row"><span class="sl">Discount</span><span class="sv" id="s_disc" style="color:var(--ok);">₹0</span></div>
        <div class="summary-row grand"><span class="sl">Total</span><span class="sv" id="s_grand">₹0</span></div>
    </div>

    <div class="act-card">
        <div class="act-card-title">Actions</div>
        <div class="act-list">
            <button type="button" onclick="submitInvoice('sent')"  class="act-btn primary">✓ Finalise &amp; Send</button>
            <button type="button" onclick="submitInvoice('draft')" class="act-btn secondary">Save Draft</button>
            <button type="button" onclick="submitInvoice('paid')"  class="act-btn success">Mark as Paid</button>
            @if($isEdit)
                @if(($s['email_enabled']??'0')==='1')
                <button type="button" onclick="openSendOnlyModal()" class="act-btn secondary">✉ Send Email</button>
                @endif
                <a href="{{ route('invoices.pdf', $invoice) }}" class="act-btn secondary" target="_blank">Download PDF</a>
                <button type="button" onclick="window.print()" class="act-btn secondary">Print</button>
                <button type="button" onclick="document.getElementById('payModal').style.display='flex'" class="act-btn secondary">Record Payment</button>
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}"
                      onsubmit="return confirm('Delete this invoice?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="act-btn danger" style="width:100%;">Delete Invoice</button>
                </form>
            @endif
            <a href="{{ route('invoices.index') }}" class="act-btn ghost">← Back to List</a>
        </div>
    </div>

    <div class="act-card">
        <div class="act-card-title">Visible Columns</div>
        @foreach(['desc'=>'Description','hsn'=>'HSN/SAC','disc'=>'Discount','unit'=>'Unit','cess'=>'Cess'] as $k=>$l)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;">
                <span style="font-size:12px;color:var(--t2);">{{ $l }}</span>
                <button type="button" class="panel-tog {{ ($visCol[$k]??0) ? 'on' : '' }}"
                        id="ptog_{{ $k }}" onclick="toggleColPanel('{{ $k }}',this)"
                        style="width:32px;height:18px;border-radius:9px;border:none;cursor:pointer;position:relative;transition:background .2s;
                               background:{{ ($visCol[$k]??0) ? 'var(--accent)' : 'var(--bdr2)' }};">
                    <span style="position:absolute;top:2px;width:14px;height:14px;border-radius:50%;background:#fff;transition:left .2s;
                                 left:{{ ($visCol[$k]??0) ? '14px' : '2px' }};"></span>
                </button>
            </div>
        @endforeach
    </div>

</div>{{-- /right panel --}}
</div>{{-- /flex wrapper --}}

{{-- ═══ Payment Modal ═══════════════════════════════════════ --}}
@if($isEdit)
<div id="payModal" class="modal-overlay" style="display:none;">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <div class="modal-title">Record Payment</div>
            <button type="button" class="modal-close" onclick="document.getElementById('payModal').style.display='none'">×</button>
        </div>
        <form method="POST" action="{{ route('invoices.payment', $invoice) }}">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Amount (₹) <span class="req">*</span></label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required
                           value="{{ number_format($invoice->balance_due, 2, '.', '') }}">
                </div>
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label class="form-label">Date <span class="req">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mode</label>
                        <select name="mode" class="form-control">
                            @foreach(['cash'=>'Cash','upi'=>'UPI','neft'=>'NEFT','rtgs'=>'RTGS','cheque'=>'Cheque','card'=>'Card','other'=>'Other'] as $v=>$l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Reference / UTR</label>
                    <input type="text" name="reference" class="form-control" placeholder="Transaction ID, cheque no…">
                </div>
                <div style="padding:8px 10px;background:var(--warn-bg);border-radius:var(--radius);font-size:12px;color:var(--warn);">
                    Balance due: <strong>{{ fmt_inr($invoice->balance_due) }}</strong>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" onclick="document.getElementById('payModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Record Payment</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ═══ Email Compose Modal ══════════════════════════════════ --}}
<div id="emailModal" class="modal-overlay" style="display:none;">
    <div class="modal-box" style="max-width:640px;width:100%;">
        <div class="modal-head">
            <div class="modal-title">Send Invoice by Email</div>
            <button type="button" class="modal-close" onclick="closeEmailModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">To <span class="req">*</span></label>
                <input type="email" id="em_to" class="form-control" placeholder="customer@example.com"
                       oninput="this.style.borderColor=''">
                <div id="em_to_err" style="font-size:12px;color:var(--err);margin-top:3px;display:none;">Valid email required.</div>
            </div>
            <div class="form-group">
                <label class="form-label">CC <span style="font-size:11px;color:var(--t4);font-weight:400;">(optional)</span></label>
                <input type="email" id="em_cc" class="form-control" placeholder="cc@example.com">
            </div>
            <div class="form-group">
                <label class="form-label">Subject <span class="req">*</span></label>
                <input type="text" id="em_subject" class="form-control">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Message</label>
                <div id="emailBodyEditor"></div>
            </div>
            <div style="margin-top:10px;padding:8px 10px;background:var(--s2);border-radius:var(--radius);font-size:11px;color:var(--t4);">
                The invoice PDF will be attached as a compressed ZIP file.
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn" onclick="closeEmailModal()">Cancel</button>
            <button type="button" class="btn" id="emSaveOnlyBtn" onclick="saveWithoutEmail()" style="display:none;">Finalise Only</button>
            <button type="button" class="btn btn-primary" onclick="doSendEmail()">✉ Send Invoice</button>
        </div>
    </div>
</div>

{{-- Standalone send-email form (edit mode, no re-save) --}}
@if($isEdit)
<form id="emailSendForm" method="POST" action="{{ route('invoices.email', $invoice) }}" style="display:none;">
    @csrf
    <input type="hidden" name="to_email"      id="es_to_email">
    <input type="hidden" name="cc_email"       id="es_cc_email">
    <input type="hidden" name="email_subject"  id="es_subject">
    <input type="hidden" name="email_body"     id="es_body">
</form>
@endif

{{-- ═══ Quick Add Item Modal ════════════════════════════════ --}}
<div id="qiModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title">Add New Item</div>
            <button type="button" class="modal-close" onclick="document.getElementById('qiModal').style.display='none'">×</button>
        </div>
        <div class="modal-body">
            <p style="font-size:12px;color:var(--t3);margin-bottom:16px;">Saved to item master for future invoices.</p>
            <div id="qi-err" style="display:none;margin-bottom:12px;padding:9px 12px;background:var(--err-bg,#fef2f2);border:1px solid var(--err,#dc2626);border-radius:var(--radius);font-size:12px;color:var(--err,#dc2626);"></div>
            <div class="form-group">
                <label class="form-label">Item Name <span class="req">*</span></label>
                <input type="text" id="qi_name" class="form-control">
            </div>
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label class="form-label">Default Rate (₹) <span class="req">*</span></label>
                    <input type="number" id="qi_rate" class="form-control" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">GST Rate</label>
                    <select id="qi_gst" class="form-control">
                        @foreach([0,5,12,18,28] as $g)
                            <option value="{{ $g }}" {{ $g===18?'selected':'' }}>{{ $g }}%</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row form-row-3">
                <div class="form-group">
                    <label class="form-label">Unit</label>
                    <select id="qi_unit" class="form-control">
                        @foreach(['Nos','Pcs','Hrs','Kg','Ltr','Month','Year','Sqft'] as $u)
                            <option>{{ $u }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">HSN / SAC</label>
                    <input type="text" id="qi_hsn" class="form-control" placeholder="998314">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select id="qi_type" class="form-control">
                        <option value="service">Service</option>
                        <option value="goods">Goods</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input type="text" id="qi_desc" class="form-control" placeholder="Brief description (optional)">
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn" onclick="document.getElementById('qiModal').style.display='none'">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveQuickItem()">Save &amp; Add to Invoice</button>
        </div>
    </div>
</div>

{{-- ═══ Quick Add Customer Modal ═══════════════════════════ --}}
<div id="qcModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title">Add New Customer</div>
            <button type="button" class="modal-close" onclick="document.getElementById('qcModal').style.display='none'">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Business Name <span class="req">*</span></label>
                <input type="text" id="qc_name" class="form-control">
                <div class="error-msg" id="qc-err-name"></div>
            </div>
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label class="form-label">GSTIN</label>
                    <input type="text" id="qc_gstin" class="form-control" placeholder="27AABCU9603R1ZX"
                           oninput="autoStateFromGstin(this.value)" maxlength="15" style="font-family:monospace;">
                    <div class="error-msg" id="qc-err-gstin"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" id="qc_phone" class="form-control" maxlength="15">
                    <div class="error-msg" id="qc-err-phone"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="text" id="qc_email" class="form-control">
                <div class="error-msg" id="qc-err-email"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <input type="text" id="qc_addr" class="form-control">
            </div>
            <div class="form-row form-row-3">
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" id="qc_city" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">State</label>
                    <select id="qc_state" class="form-control" onchange="qcSetStateCode(this.value)">
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
                    <input type="text" id="qc_sc" class="form-control" placeholder="27">
                    <div class="form-hint">For CGST/IGST</div>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn" onclick="document.getElementById('qcModal').style.display='none'">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveQuickCust()">Save Customer</button>
        </div>
    </div>
</div>

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<style>
.hidden-col { display: none !important; }
#emailBodyEditor .ql-editor { min-height:160px; font-size:13px; font-family:var(--font); line-height:1.6; }
#emailBodyEditor .ql-toolbar.ql-snow { border-radius:var(--radius) var(--radius) 0 0; border-color:var(--bdr2); background:var(--s2); }
#emailBodyEditor .ql-container.ql-snow { border-color:var(--bdr2); border-radius:0 0 var(--radius) var(--radius); }
#emailBodyEditor .ql-toolbar.ql-snow button,
#emailBodyEditor .ql-toolbar.ql-snow .ql-picker-label { color:var(--t2); }
#emailBodyEditor .ql-toolbar.ql-snow button:hover,
#emailBodyEditor .ql-toolbar.ql-snow .ql-picker-label:hover,
#emailBodyEditor .ql-toolbar.ql-snow button.ql-active { color:var(--accent); }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.js"></script>
<script>
const CSRF    = document.querySelector('meta[name="csrf-token"]').content;
const MY_SC   = '{{ $s["company_state_code"] ?? "27" }}';
const HDR     = {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'};
const STATE_MAP = {'01':'Jammu and Kashmir','02':'Himachal Pradesh','03':'Punjab','04':'Chandigarh','05':'Uttarakhand','06':'Haryana','07':'Delhi','08':'Rajasthan','09':'Uttar Pradesh','10':'Bihar','11':'Sikkim','12':'Arunachal Pradesh','13':'Nagaland','14':'Manipur','15':'Mizoram','16':'Tripura','17':'Meghalaya','18':'Assam','19':'West Bengal','20':'Jharkhand','21':'Odisha','22':'Chhattisgarh','23':'Madhya Pradesh','24':'Gujarat','26':'Dadra and Nagar Haveli and Daman and Diu','27':'Maharashtra','29':'Karnataka','30':'Goa','31':'Lakshadweep','32':'Kerala','33':'Tamil Nadu','34':'Puducherry','35':'Andaman and Nicobar Islands','36':'Telangana','37':'Andhra Pradesh','38':'Ladakh'};
const STATE_CODES_QC = {'Andaman and Nicobar Islands':'35','Andhra Pradesh':'37','Arunachal Pradesh':'12','Assam':'18','Bihar':'10','Chandigarh':'04','Chhattisgarh':'22','Dadra and Nagar Haveli and Daman and Diu':'26','Delhi':'07','Goa':'30','Gujarat':'24','Haryana':'06','Himachal Pradesh':'02','Jammu and Kashmir':'01','Jharkhand':'20','Karnataka':'29','Kerala':'32','Ladakh':'38','Lakshadweep':'31','Madhya Pradesh':'23','Maharashtra':'27','Manipur':'14','Meghalaya':'17','Mizoram':'15','Nagaland':'13','Odisha':'21','Puducherry':'34','Punjab':'03','Rajasthan':'08','Sikkim':'11','Tamil Nadu':'33','Telangana':'36','Tripura':'16','Uttar Pradesh':'09','Uttarakhand':'05','West Bengal':'19'};

let rowCount = 0, qiRowIdx = null;
let visCol   = @json($visCol);
let searchT  = {};

/* ── Boot ── */
document.addEventListener('DOMContentLoaded', () => {
    const seed = @json($rows);
    if (!seed.length) addRow();
    else seed.forEach(r => addRow(r));
    syncVisColInput();
    updateGstTag();

    document.getElementById('custSearchInput')
        .addEventListener('input', debounce(searchCustomers, 260));
    document.getElementById('custSearchInput')
        .addEventListener('focus', () => {
            if (document.getElementById('custSearchInput').value) searchCustomers();
        });
    document.addEventListener('click', e => {
        if (!e.target.closest('#custSearchWrap'))  document.getElementById('custDd').style.display='none';
        if (!e.target.closest('.item-cell'))        document.querySelectorAll('.item-dd').forEach(d=>d.style.display='none');
    });
    document.getElementById('posInput').addEventListener('input', () => {
        document.getElementById('f_pos').value = document.getElementById('posInput').value;
    });
});

/* ── Row management ── */
function addRow(d={}) {
    const idx = rowCount++;
    const tbody = document.getElementById('itemsBody');
    const num   = tbody.children.length + 1;
    const tr    = document.createElement('tr');
    tr.dataset.idx = idx;

    const vDesc = visCol.desc ? '' : ' hidden-col';
    const vHsn  = visCol.hsn  ? '' : ' hidden-col';
    const vDisc = visCol.disc ? '' : ' hidden-col';
    const vUnit = visCol.unit ? '' : ' hidden-col';
    const vCess = visCol.cess ? '' : ' hidden-col';

    tr.innerHTML = `
    <td class="td-num">${num}</td>
    <td style="position:relative;" class="item-cell">
      <input class="td-inp" type="text" name="rows[${idx}][item_name]"
             value="${esc(d.item_name||d.name||'')}" placeholder="Type to search…" autocomplete="off"
             oninput="onItemType(this,${idx})" onfocus="onItemType(this,${idx})">
      <input type="hidden" name="rows[${idx}][item_id]" class="f_iid" value="${d.item_id||''}">
      <div class="item-dd" id="idd_${idx}" style="display:none;"></div>
    </td>
    <td class="col-desc${vDesc}">
      <input class="td-inp" type="text" name="rows[${idx}][description]"
             value="${esc(d.description||d.desc||'')}" placeholder="Description">
    </td>
    <td class="col-hsn${vHsn}">
      <input class="td-inp" type="text" name="rows[${idx}][hsn_sac]"
             value="${esc(d.hsn_sac||d.hsn||'')}" placeholder="HSN/SAC" style="font-size:11px;">
    </td>
    <td>
      <input class="td-inp r" type="number" name="rows[${idx}][qty]"
             value="${d.qty||1}" min="0.001" step="0.001" style="width:58px;" oninput="recalc()">
    </td>
    <td class="col-unit${vUnit}">
      <select class="td-sel" name="rows[${idx}][unit]">
        ${['Nos','Pcs','Hrs','Kg','Ltr','Month','Year','Sqft'].map(u=>`<option${(d.unit||'Nos')===u?' selected':''}>${u}</option>`).join('')}
      </select>
    </td>
    <td>
      <input class="td-inp r" type="number" name="rows[${idx}][rate]"
             value="${d.rate||0}" min="0" step="0.01" oninput="recalc()">
    </td>
    <td class="col-disc${vDisc}">
      <input class="td-inp r" type="number" name="rows[${idx}][discount_percent]"
             value="${d.discount_percent||0}" min="0" max="100" step="0.01"
             placeholder="0" style="width:62px;" oninput="recalc()">
    </td>
    <td class="td-calc r" id="td_tax_${idx}">₹0.00</td>
    <td class="c">
      <select class="td-sel" name="rows[${idx}][gst_rate]" onchange="recalc()"
              style="width:50px;text-align:center;background:var(--accent-l);color:var(--accent-t);font-weight:600;">
        ${[0,5,12,18,28].map(g=>`<option value="${g}"${(d.gst_rate||18)==g?' selected':''}>${g}%</option>`).join('')}
      </select>
    </td>
    <td class="td-calc r" id="td_taxamt_${idx}">₹0.00</td>
    <td class="td-calc r td-bold" id="td_tot_${idx}">₹0.00</td>
    <td><button type="button" class="del-row-btn" onclick="delRow(this)">×</button></td>`;

    tbody.appendChild(tr);
    renumber();
    recalc();
}

function delRow(btn) { btn.closest('tr').remove(); renumber(); recalc(); }
function renumber()  { document.querySelectorAll('#itemsBody tr').forEach((t,i)=>t.querySelector('.td-num').textContent=i+1); }

/* ── Item auto-suggest ── */
function onItemType(inp, idx) {
    clearTimeout(searchT[idx]);
    const q = inp.value.trim();
    if (!q) { hideDd(idx); return; }
    searchT[idx] = setTimeout(() => fetchItems(q, idx, inp.value, inp), 240);
}
async function fetchItems(q, idx, raw, inp) {
    const r = await fetch(`/items/search?q=${encodeURIComponent(q)}`, {headers:HDR});
    const d = await r.json();
    showItemDd(idx, d, raw, inp);
}
function showItemDd(idx, items, raw, inp) {
    const dd = document.getElementById(`idd_${idx}`);
    if (!dd) return;
    let h = items.map(it => `
      <div class="item-opt" onmousedown="pickItem(${idx},'${encodeURIComponent(JSON.stringify(it))}')">
        <div class="io-name">${esc(it.name)}</div>
        <div class="io-meta">₹${fmtN(it.rate)} &nbsp;·&nbsp; ${it.gst_rate}% GST &nbsp;·&nbsp; HSN: ${it.hsn_sac||'—'}</div>
      </div>`).join('');
    const exact = items.find(i=>i.name.toLowerCase()===raw.toLowerCase());
    if (!exact && raw.trim()) {
        h += `<div class="item-opt io-create" onmousedown="openQiModal(${idx},'${raw.replace(/'/g,"\\'")}')">
               <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M6 3v6M3 6h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
               Create "${raw}"
             </div>`;
    }
    dd.innerHTML = h || '';
    if (h) {
        const rect = inp.getBoundingClientRect();
        dd.style.top  = (rect.bottom + window.scrollY + 2) + 'px';
        dd.style.left = (rect.left  + window.scrollX) + 'px';
        dd.style.display = 'block';
    } else {
        dd.style.display = 'none';
    }
    document.querySelectorAll('.item-dd').forEach(d=>{ if(d.id!==`idd_${idx}`) d.style.display='none'; });
}
function pickItem(idx, enc) {
    const it = JSON.parse(decodeURIComponent(enc));
    const tr = document.querySelector(`#itemsBody tr[data-idx="${idx}"]`);
    if (!tr) return;
    tr.querySelector(`[name="rows[${idx}][item_name]"]`).value    = it.name;
    tr.querySelector(`[name="rows[${idx}][description]"]`).value  = it.description||'';
    tr.querySelector(`[name="rows[${idx}][hsn_sac]"]`).value      = it.hsn_sac||'';
    tr.querySelector(`[name="rows[${idx}][rate]"]`).value         = it.rate;
    tr.querySelector(`[name="rows[${idx}][gst_rate]"]`).value     = it.gst_rate;
    tr.querySelector(`[name="rows[${idx}][unit]"]`).value         = it.unit;
    tr.querySelector('.f_iid').value                               = it.id;
    hideDd(idx);
    recalc();
}
function hideDd(idx) { const d=document.getElementById(`idd_${idx}`); if(d) d.style.display='none'; }

/* ── Customer search ── */
async function searchCustomers() {
    const q  = document.getElementById('custSearchInput').value;
    const r  = await fetch(`/customers/search?q=${encodeURIComponent(q)}`, {headers:HDR});
    const cs = await r.json();
    const dd = document.getElementById('custDd');
    let h = cs.map(c=>`
      <div class="cust-opt" onmousedown="pickCustomer('${encodeURIComponent(JSON.stringify(c))}')">
        <div class="co-name">${esc(c.name)}</div>
        <div class="co-detail">${c.billing_city||''} · ${c.gstin||''}</div>
      </div>`).join('');
    h += `<div class="cust-opt co-create" onmousedown="openQcModal('${q.replace(/'/g,"\\'")}')">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M6 3v6M3 6h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            Create "${q||'new customer'}"
          </div>`;
    dd.innerHTML = h;
    dd.style.display = 'block';
}
function pickCustomer(enc) {
    const c = JSON.parse(decodeURIComponent(enc));
    document.getElementById('f_cust_id').value    = c.id;
    document.getElementById('f_cust_name').value  = c.name;
    document.getElementById('f_cust_gstin').value = c.gstin||'';
    document.getElementById('f_cust_email').value = c.email||'';
    document.getElementById('f_cust_addr').value  = c.billing_address||'';
    document.getElementById('f_cust_city').value  = c.billing_city||'';
    document.getElementById('f_cust_state').value = c.billing_state||'';
    document.getElementById('f_cust_sc').value    = c.billing_state_code||'';
    document.getElementById('f_pos').value        = c.billing_state||'';
    document.getElementById('f_pos_code').value   = c.billing_state_code||'';
    document.getElementById('posInput').value     = `${c.billing_state||''} (${c.billing_state_code||''})`;
    document.getElementById('cf_name').textContent   = c.name;
    document.getElementById('cf_detail').textContent = `${c.billing_address||''}, ${c.billing_city||''}`;
    document.getElementById('cf_gstin').textContent  = c.gstin||'';
    document.getElementById('custSearchWrap').style.display = 'none';
    document.getElementById('custFilled').style.display     = 'block';
    document.getElementById('custDd').style.display         = 'none';
    updateGstTag(); recalc();
}
function clearCustomer() {
    ['f_cust_id','f_cust_name','f_cust_gstin','f_cust_addr','f_cust_city','f_cust_state','f_cust_sc','f_cust_email'].forEach(id => document.getElementById(id).value='');
    document.getElementById('f_pos').value      = '';
    document.getElementById('f_pos_code').value = '';
    document.getElementById('posInput').value   = '';
    document.getElementById('custSearchInput').value = '';
    document.getElementById('custSearchWrap').style.display = 'block';
    document.getElementById('custFilled').style.display     = 'none';
    updateGstTag(); recalc();
}

/* ── GST type tag ── */
function updateGstTag() {
    const sc    = document.getElementById('f_cust_sc').value.trim();
    const intra = !sc || sc === MY_SC;
    document.getElementById('f_intra').value = intra ? '1' : '0';
    const tag = document.getElementById('gstTypeTag');
    tag.textContent = intra ? '→ CGST + SGST' : '→ IGST';
    tag.className   = 'supply-tag ' + (intra ? 'supply-intra' : 'supply-inter');
    tag.style.display = 'inline';
    recalc();
}

/* ── Calculations ── */
function recalc() {
    const isIntra = document.getElementById('f_intra').value === '1';
    let sub=0, ld=0, tt=0;
    const buckets = {};

    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const idx  = tr.dataset.idx;
        const qty  = parseFloat(tr.querySelector(`[name="rows[${idx}][qty]"]`)?.value)||0;
        const rate = parseFloat(tr.querySelector(`[name="rows[${idx}][rate]"]`)?.value)||0;
        const disc = parseFloat(tr.querySelector(`[name="rows[${idx}][discount_percent]"]`)?.value)||0;
        const gst  = parseFloat(tr.querySelector(`[name="rows[${idx}][gst_rate]"]`)?.value)||0;
        const gross   = qty * rate;
        const discAmt = gross * disc / 100;
        const taxable = gross - discAmt;
        const tax     = taxable * gst / 100;
        const total   = taxable + tax;
        sub += taxable; ld += discAmt; tt += tax;
        if (gst > 0) buckets[gst] = (buckets[gst]||0) + tax;
        setText(`td_tax_${idx}`,  '₹'+fmtN(taxable));
        setText(`td_taxamt_${idx}`,'₹'+fmtN(tax));
        setText(`td_tot_${idx}`,  '₹'+fmtN(total));
    });

    const dv   = parseFloat(document.getElementById('discVal').value)||0;
    const dt   = document.getElementById('discType').value;
    const invD = dt==='percent' ? sub*dv/100 : Math.min(dv,sub);
    const ratio= sub>0 ? (sub-invD)/sub : 1;
    const netT = tt*ratio;
    const raw  = (sub-invD)+netT;
    const grand= Math.round(raw);
    const rOff = grand-raw;

    setText('t_subtotal','₹'+fmtN(sub));
    setText('t_linedisc','-₹'+fmtN(ld));
    setText('t_invdisc', '-₹'+fmtN(invD));
    setText('t_totaltax','₹'+fmtN(netT));
    setText('t_roundoff',(rOff>=0?'+':'')+fmtN(rOff));
    setText('t_grand',   '₹'+fmtN(grand));
    setText('t_words',   n2w(grand));

    let gh='';
    Object.entries(buckets).forEach(([r,t])=>{
        const nt=t*ratio;
        gh += isIntra
            ? `<div class="gst-row"><span class="gl">CGST @ ${r/2}%</span><span class="gv">₹${fmtN(nt/2)}</span></div>
               <div class="gst-row"><span class="gl">SGST @ ${r/2}%</span><span class="gv">₹${fmtN(nt/2)}</span></div>`
            : `<div class="gst-row"><span class="gl">IGST @ ${r}%</span><span class="gv">₹${fmtN(nt)}</span></div>`;
    });
    document.getElementById('gstRows').innerHTML = gh;

    const rc = document.querySelectorAll('#itemsBody tr').length;
    setText('s_items', rc+' line'+(rc!==1?'s':''));
    setText('s_sub',   '₹'+fmtNS(sub));
    setText('s_tax',   '₹'+fmtNS(netT));
    setText('s_disc',  '-₹'+fmtNS(invD+ld));
    setText('s_grand', '₹'+fmtNS(grand));
}

/* ── Column toggle ── */
function toggleColChip(k, el) { visCol[k]=!visCol[k]; applyCol(); }
function toggleColPanel(k, btn) { visCol[k]=!visCol[k]; applyCol(); }
function applyCol() {
    ['desc','hsn','disc','unit','cess'].forEach(k=>{
        const on=!!visCol[k];
        document.querySelectorAll(`.col-${k}`).forEach(el=>el.classList.toggle('hidden-col',!on));
        const chip=document.querySelector(`.col-chip[data-col="${k}"]`);
        if(chip) chip.classList.toggle('on',on);
        const pt=document.getElementById(`ptog_${k}`);
        if(pt){ pt.style.background=on?'var(--accent)':'var(--bdr2)'; pt.querySelector('span').style.left=on?'14px':'2px'; }
    });
    syncVisColInput();
}
function syncVisColInput() {
    document.getElementById('visibleColsInput').value = JSON.stringify(visCol);
}

/* ── Quick add item ── */
function openQiModal(idx, name) {
    qiRowIdx = idx;
    document.getElementById('qi_name').value = name||'';
    ['qi_rate','qi_hsn','qi_desc'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('qi_gst').value  = '18';
    document.getElementById('qi_unit').value = 'Nos';
    document.getElementById('qi_type').value = 'service';
    const err = document.getElementById('qi-err');
    err.style.display = 'none';
    err.textContent   = '';
    document.getElementById('qiModal').style.display='flex';
}
async function saveQuickItem() {
    const name = document.getElementById('qi_name').value.trim();
    const rate = parseFloat(document.getElementById('qi_rate').value)||0;
    const errEl = document.getElementById('qi-err');
    errEl.style.display = 'none';

    if (!name || !rate) {
        errEl.textContent   = !name ? 'Item name is required.' : 'Rate must be greater than zero.';
        errEl.style.display = 'block';
        return;
    }

    const payload = {
        name, rate, gst_rate: parseFloat(document.getElementById('qi_gst').value)||18,
        unit:        document.getElementById('qi_unit').value,
        hsn_sac:     document.getElementById('qi_hsn').value.trim(),
        description: document.getElementById('qi_desc').value.trim(),
        type:        document.getElementById('qi_type').value,
    };

    let res, json;
    try {
        res  = await fetch('/items', {method:'POST', headers:HDR, body:JSON.stringify(payload)});
        json = await res.json();
    } catch (e) {
        errEl.textContent   = 'Network error — please try again.';
        errEl.style.display = 'block';
        return;
    }

    if (!res.ok) {
        const msgs = json.errors
            ? Object.values(json.errors).flat().join(' ')
            : (json.message || 'Could not save item. Please try again.');
        errEl.textContent   = msgs;
        errEl.style.display = 'block';
        return;
    }

    if (qiRowIdx !== null) pickItem(qiRowIdx, encodeURIComponent(JSON.stringify(json)));
    document.getElementById('qiModal').style.display = 'none';
}

/* ── Quick add customer ── */
function qcClearErrors() {
    ['qc-err-name','qc-err-gstin','qc-err-phone','qc-err-email'].forEach(id=>{
        document.getElementById(id).textContent='';
    });
    ['qc_name','qc_gstin','qc_phone','qc_email'].forEach(id=>{
        document.getElementById(id).style.borderColor='';
    });
}
function qcSetError(fieldId, errId, msg) {
    document.getElementById(errId).textContent = msg;
    document.getElementById(fieldId).style.borderColor = 'var(--err)';
}
function qcSetStateCode(state) {
    document.getElementById('qc_sc').value = STATE_CODES_QC[state] || '';
}
function openQcModal(name) {
    qcClearErrors();
    document.getElementById('qc_name').value = name||'';
    ['qc_gstin','qc_phone','qc_email','qc_addr','qc_city','qc_sc'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('qc_state').value = '';
    document.getElementById('qcModal').style.display='flex';
    document.getElementById('custDd').style.display='none';
}
function autoStateFromGstin(g) {
    if(g.length>=2){
        const sc=g.slice(0,2);
        document.getElementById('qc_sc').value=sc;
        if(STATE_MAP[sc]) document.getElementById('qc_state').value=STATE_MAP[sc];
    }
}
function validateQcForm() {
    qcClearErrors();
    let valid = true;
    const name  = document.getElementById('qc_name').value.trim();
    const gstin = document.getElementById('qc_gstin').value.trim().toUpperCase();
    const phone = document.getElementById('qc_phone').value.trim();
    const email = document.getElementById('qc_email').value.trim();

    if (!name) {
        qcSetError('qc_name','qc-err-name','Customer name is required.');
        valid = false;
    }
    if (gstin && !/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/.test(gstin)) {
        qcSetError('qc_gstin','qc-err-gstin','Invalid GSTIN format (e.g. 27AABCT1234R1ZX).');
        valid = false;
    }
    if (phone && !/^[0-9+\-\s]{7,15}$/.test(phone)) {
        qcSetError('qc_phone','qc-err-phone','Enter a valid phone number.');
        valid = false;
    }
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        qcSetError('qc_email','qc-err-email','Enter a valid email address.');
        valid = false;
    }
    if (!valid) {
        const first = document.querySelector('#qcModal .error-msg:not(:empty)');
        if (first) first.previousElementSibling.focus();
    }
    return valid;
}

async function saveQuickCust() {
    if (!validateQcForm()) return;

    const payload = {
        name:             document.getElementById('qc_name').value.trim(),
        gstin:            document.getElementById('qc_gstin').value.trim().toUpperCase(),
        email:            document.getElementById('qc_email').value.trim(),
        phone:            document.getElementById('qc_phone').value.trim(),
        billing_address:  document.getElementById('qc_addr').value.trim(),
        billing_city:     document.getElementById('qc_city').value.trim(),
        billing_state:    document.getElementById('qc_state').value,
        billing_state_code: document.getElementById('qc_sc').value.trim(),
    };

    try {
        const r = await fetch('/customers', {method:'POST', headers:HDR, body:JSON.stringify(payload)});
        if (!r.ok) {
            const err = await r.json();
            if (err.errors) {
                if (err.errors.name)  qcSetError('qc_name',  'qc-err-name',  err.errors.name[0]);
                if (err.errors.gstin) qcSetError('qc_gstin', 'qc-err-gstin', err.errors.gstin[0]);
                if (err.errors.phone) qcSetError('qc_phone', 'qc-err-phone', err.errors.phone[0]);
                if (err.errors.email) qcSetError('qc_email', 'qc-err-email', err.errors.email[0]);
            }
            return;
        }
        const c = await r.json();
        pickCustomer(encodeURIComponent(JSON.stringify(c)));
        document.getElementById('qcModal').style.display='none';
    } catch(e) {
        console.error('Save customer failed:', e);
    }
}

/* ── Misc helpers ── */
function setStatus(v) { document.querySelector('[name="status"]').value=v; }

/* ── Email modal ── */
const EMAIL_ENABLED = {{ ($s['email_enabled']??'0')==='1' ? 'true' : 'false' }};
const EMAIL_SUBJECT = @json($s['email_subject'] ?? 'Invoice {invoice_number} from {company_name}');
const EMAIL_BODY    = @json($s['email_body'] ?? '<p>Dear {customer_name},</p><p>Please find attached <strong>Invoice {invoice_number}</strong> from <strong>{company_name}</strong>.</p><p><strong>Amount:</strong> {amount}<br><strong>Due Date:</strong> {due_date}</p><p>The invoice PDF is included as a compressed attachment.</p><p>Regards,<br>{company_name}</p>');
const COMPANY_NAME  = @json($s['company_name'] ?? '');

let _emailMode = null; // 'send_and_save' | 'send_only'

const emailQuill = new Quill('#emailBodyEditor', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link'],
            ['clean'],
        ],
    },
});

function _fillEmailPlaceholders(str) {
    const custName = document.getElementById('f_cust_name').value || '';
    const invNum   = document.getElementById('invNumber').value   || '';
    const dueDate  = document.querySelector('[name="due_date"]').value || '';
    const amount   = document.getElementById('s_grand').textContent || '';
    return str
        .replace(/{customer_name}/g, custName)
        .replace(/{invoice_number}/g, invNum)
        .replace(/{company_name}/g, COMPANY_NAME)
        .replace(/{amount}/g, amount)
        .replace(/{due_date}/g, dueDate);
}

function openEmailModal(status) {
    _emailMode = 'send_and_save';
    document.getElementById('emSaveOnlyBtn').style.display = '';
    _pendingStatus = status;
    document.getElementById('em_to').value      = document.getElementById('f_cust_email').value || '';
    document.getElementById('em_cc').value      = '';
    document.getElementById('em_subject').value = _fillEmailPlaceholders(EMAIL_SUBJECT);
    emailQuill.clipboard.dangerouslyPasteHTML(_fillEmailPlaceholders(EMAIL_BODY));
    document.getElementById('em_to_err').style.display = 'none';
    document.getElementById('emailModal').style.display = 'flex';
}

function openSendOnlyModal() {
    _emailMode = 'send_only';
    document.getElementById('emSaveOnlyBtn').style.display = 'none';
    document.getElementById('em_to').value      = document.getElementById('f_cust_email').value || '';
    document.getElementById('em_cc').value      = '';
    document.getElementById('em_subject').value = _fillEmailPlaceholders(EMAIL_SUBJECT);
    emailQuill.clipboard.dangerouslyPasteHTML(_fillEmailPlaceholders(EMAIL_BODY));
    document.getElementById('em_to_err').style.display = 'none';
    document.getElementById('emailModal').style.display = 'flex';
}

function closeEmailModal() {
    document.getElementById('emailModal').style.display = 'none';
    _emailMode = null;
}

function saveWithoutEmail() {
    document.getElementById('sendEmailFlag').value = '0';
    setStatus(_pendingStatus || 'sent');
    syncVisColInput();
    closeEmailModal();
    document.getElementById('invoiceForm').requestSubmit();
}

function doSendEmail() {
    const to = document.getElementById('em_to').value.trim();
    if (!to || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) {
        document.getElementById('em_to_err').style.display = '';
        document.getElementById('em_to').focus();
        return;
    }
    const subject = document.getElementById('em_subject').value.trim();
    const body    = emailQuill.root.innerHTML;
    const cc      = document.getElementById('em_cc').value.trim();

    if (_emailMode === 'send_only') {
        document.getElementById('es_to_email').value = to;
        document.getElementById('es_cc_email').value = cc;
        document.getElementById('es_subject').value  = subject;
        document.getElementById('es_body').value     = body;
        closeEmailModal();
        document.getElementById('emailSendForm').submit();
    } else {
        document.getElementById('h_to_email').value      = to;
        document.getElementById('h_cc_email').value      = cc;
        document.getElementById('h_email_subject').value = subject;
        document.getElementById('h_email_body').value    = body;
        document.getElementById('sendEmailFlag').value   = '1';
        setStatus(_pendingStatus || 'sent');
        syncVisColInput();
        closeEmailModal();
        document.getElementById('invoiceForm').requestSubmit();
    }
}

let _pendingStatus = null;

function validateInvoiceForm() {
    const errBox  = document.getElementById('client-errors');
    const errList = document.getElementById('client-errors-list');
    const errs    = [];

    // Clear previous highlights
    document.querySelectorAll('#itemsBody .td-inp').forEach(el => el.style.borderColor = '');
    const custSearch = document.getElementById('custSearchInput');
    if (custSearch) custSearch.style.borderColor = '';
    errBox.style.display = 'none';
    errList.innerHTML    = '';

    // Customer must be selected
    if (!document.getElementById('f_cust_name').value.trim()) {
        if (custSearch) custSearch.style.borderColor = 'var(--err, #dc2626)';
        errs.push('Please select a customer.');
    }

    // Must have at least one row
    const rows = document.querySelectorAll('#itemsBody tr');
    if (rows.length === 0) {
        errs.push('Please add at least one line item.');
    } else {
        rows.forEach((tr, i) => {
            const idx    = tr.dataset.idx;
            const nameEl = tr.querySelector(`[name="rows[${idx}][item_name]"]`);
            const qtyEl  = tr.querySelector(`[name="rows[${idx}][qty]"]`);

            if (nameEl && !nameEl.value.trim()) {
                nameEl.style.borderColor = 'var(--err, #dc2626)';
                errs.push(`Row ${i + 1}: Item name is required.`);
            }
            if (qtyEl && (parseFloat(qtyEl.value) || 0) < 0.001) {
                qtyEl.style.borderColor = 'var(--err, #dc2626)';
                errs.push(`Row ${i + 1}: Quantity must be greater than zero.`);
            }
        });
    }

    if (errs.length) {
        errList.innerHTML    = errs.map(e => `<li style="font-size:13px;">${esc(e)}</li>`).join('');
        errBox.style.display = 'block';
        errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return false;
    }
    return true;
}

function submitInvoice(status) {
    if (!validateInvoiceForm()) return;
    if (status === 'sent' && EMAIL_ENABLED) {
        openEmailModal(status);
        return;
    }
    setStatus(status);
    syncVisColInput();
    document.getElementById('invoiceForm').requestSubmit();
}

// Close email modal on backdrop click
document.addEventListener('click', e => {
    const modal = document.getElementById('emailModal');
    if (e.target === modal) closeEmailModal();
});
function setText(id,v) { const e=document.getElementById(id); if(e) e.textContent=v; }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function debounce(fn,ms){ let t; return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),ms);}; }
function fmtN(n) {
    n=parseFloat(n)||0;
    let [i,d]=n.toFixed(2).split('.');
    if(i.length>3){ let l=i.slice(-3),r=i.slice(0,-3).replace(/\B(?=(\d{2})+(?!\d))/g,','); i=r+','+l; }
    return i+'.'+d;
}
function fmtNS(n) {
    n=Math.round(parseFloat(n)||0); let s=String(n);
    if(s.length>3){ let l=s.slice(-3),r=s.slice(0,-3).replace(/\B(?=(\d{2})+(?!\d))/g,','); s=r+','+l; }
    return s;
}
function n2w(num) {
    num=Math.round(num||0);
    if(!num) return 'Zero Rupees Only';
    const o=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    const t=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    const h=n=>{if(!n)return'';if(n<20)return o[n]+' ';if(n<100)return t[~~(n/10)]+' '+(n%10?o[n%10]+' ':'');return o[~~(n/100)]+' Hundred '+(n%100?h(n%100):'')+' ';};
    let r='',n=num;
    if(n>=10000000){r+=h(~~(n/10000000))+'Crore ';n%=10000000;}
    if(n>=100000){r+=h(~~(n/100000))+'Lakh ';n%=100000;}
    if(n>=1000){r+=h(~~(n/1000))+'Thousand ';n%=1000;}
    r+=h(n);
    return 'Rupees '+r.trim()+' Only';
}
</script>
@endpush
@endsection
