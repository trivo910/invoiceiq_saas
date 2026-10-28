<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 18mm 10mm 18mm 20mm; }
* { margin: 1; padding: 1; box-sizing: border-box; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.5; }
</style>
@if($accentTemplate ?? false)
<style>
body { border-top: 5px solid {{ $brandColor }}; padding-top: 14px; }
thead th { color: {{ $brandColor }} !important; }
</style>
@endif
</head>
<body>

{{-- ══════════════════════════════════════════
     HEADER
     Left : company info (text left, logo right)
     Right: TAX INVOICE label, number, status
═══════════════════════════════════════════ --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr>

    {{-- Left column: company text | logo --}}
    <td width="62%" valign="top"
        style="padding-right:16px; padding-bottom:14px; border-bottom:2px solid #111318;">
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        <tr>
          {{-- Company name + details --}}
          <td valign="top" style="padding-right:10px;">
            <div style="font-size:19px; font-weight:700; color:#111318; margin-bottom:4px;">
              {{ $settings['company_name'] ?? '' }}
            </div>
            <div style="font-size:11px; color:#555; line-height:1.8;">
              @php
                $addrParts = array_filter([
                  $settings['company_address'] ?? '',
                  $settings['company_city']    ?? '',
                  $settings['company_state']   ?? '',
                ]);
              @endphp
              {{ implode(', ', $addrParts) }}<br>
              @if(!empty($settings['company_phone']))
                Ph: {{ $settings['company_phone'] }}{{ !empty($settings['company_email']) ? '   |   ' : '' }}
              @endif
              {{ $settings['company_email'] ?? '' }}<br>
              @if(!empty($settings['company_gstin']))
                GSTIN: <strong>{{ $settings['company_gstin'] }}</strong>{{ !empty($settings['company_pan']) ? '   |   ' : '' }}
              @endif
              @if(!empty($settings['company_pan']))
                PAN: {{ $settings['company_pan'] }}
              @endif
            </div>
          </td>
          {{-- Logo aligned to right of company details --}}
          @if($logoBase64)
          <td width="130" valign="middle" align="right">
            <img src="{{ $logoBase64 }}"
                 style="display:block; max-height:60px; max-width:125px; margin-left:auto;">
          </td>
          @endif
        </tr>
      </table>
    </td>

    {{-- Right column: TAX INVOICE + number + status only --}}
    <td width="38%" valign="top" align="right"
        style="padding-left:16px; padding-bottom:14px; border-bottom:2px solid #111318;">
      <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:1.8px; margin-bottom:5px;">
        Tax Invoice
      </div>
      <div style="font-size:23px; font-weight:700; color:#111318; line-height:1.15;">
        {{ $invoice->number }}
      </div>
      <div style="margin-top:7px;">
        <span style="display:inline-block; font-size:11px; font-weight:700;
                     text-transform:uppercase; padding:3px 10px; letter-spacing:.5px;
                     background:#eceef4; color:#555;">
          {{ ucfirst($invoice->effective_status) }}
        </span>
      </div>
    </td>

  </tr>
</table>

{{-- ══════════════════════════════════════════
     PARTIES  –  Bill To | Dates + Place of Supply
═══════════════════════════════════════════ --}}
<table width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:collapse; margin-top:14px; margin-bottom:14px;">
  <tr>
    <td width="55%" valign="top"
        style="padding:8px 14px 8px 0; border-right:1px solid #dde0e8;">
      <div style="font-size:9.5px; font-weight:700; text-transform:uppercase;
                  letter-spacing:1px; color:#888; margin-bottom:5px;">Bill To</div>
      <div style="font-size:14px; font-weight:700; color:#111318; margin-bottom:3px;">
        {{ $invoice->customer_name }}
      </div>
      <div style="font-size:11px; color:#555; line-height:1.7;">
        {{ $invoice->customer_billing_address }}
        @php
          $locParts = array_filter([$invoice->customer_city, $invoice->customer_state]);
        @endphp
        @if(count($locParts)) <br>{{ implode(', ', $locParts) }} @endif
      </div>
      @if($invoice->customer_gstin)
        <div style="font-size:11px; color:#1a56db; font-weight:600; margin-top:4px;">
          GSTIN: {{ $invoice->customer_gstin }}
        </div>
      @endif
    </td>
    <td width="45%" valign="top" style="padding:8px 0 8px 16px;">
      {{-- Dates --}}
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom:8px;">
        <tr>
          <td style="font-size:9.5px; font-weight:700; text-transform:uppercase;
                     letter-spacing:1px; color:#888; padding-bottom:4px; width:85px;">Invoice Date</td>
          <td style="font-size:11px; color:#111318; padding-bottom:4px;">
            {{ $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') : '—' }}
          </td>
        </tr>
        @if($invoice->due_date)
        <tr>
          <td style="font-size:9.5px; font-weight:700; text-transform:uppercase;
                     letter-spacing:1px; color:#888; padding-bottom:4px;">Due Date</td>
          <td style="font-size:11px; color:#d62b2b; font-weight:600; padding-bottom:4px;">
            {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}
          </td>
        </tr>
        @endif
      </table>
      {{-- Place of Supply --}}
      <div style="font-size:9.5px; font-weight:700; text-transform:uppercase;
                  letter-spacing:1px; color:#888; margin-bottom:3px;">Place of Supply</div>
      <div style="font-size:11px; color:#333; margin-bottom:5px;">
        {{ $invoice->place_of_supply ?: $invoice->customer_state }}
      </div>
      <div style="font-size:11px; font-weight:700;
                  color:{{ $invoice->is_intra_state ? '#0c7a59' : '#1a56db' }};">
        {{ $invoice->is_intra_state ? 'CGST + SGST Applicable' : 'IGST Applicable' }}
      </div>
    </td>
  </tr>
</table>

{{-- ══════════════════════════════════════════
     LINE ITEMS TABLE
═══════════════════════════════════════════ --}}
<table width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:collapse; margin-bottom:10px;">
  <thead>
    <tr style="background:#111318;">
      <th width="18"  align="left"  style="padding:7px 8px; font-size:9.5px; color:#fff; text-transform:uppercase; letter-spacing:.5px; font-weight:700;">#</th>
      <th            align="left"  style="padding:7px 8px; font-size:9.5px; color:#fff; text-transform:uppercase; letter-spacing:.5px; font-weight:700;">Item / Service</th>
      <th width="54"  align="left"  style="padding:7px 8px; font-size:9.5px; color:#fff; text-transform:uppercase; letter-spacing:.5px; font-weight:700;">HSN/SAC</th>
      <th width="40"  align="right" style="padding:7px 8px; font-size:9.5px; color:#fff; text-transform:uppercase; letter-spacing:.5px; font-weight:700;">Qty</th>
      <th width="54"  align="right" style="padding:7px 8px; font-size:9.5px; color:#fff; text-transform:uppercase; letter-spacing:.5px; font-weight:700;">Rate</th>
      <th width="33"  align="right" style="padding:7px 8px; font-size:9.5px; color:#fff; text-transform:uppercase; letter-spacing:.5px; font-weight:700;">Disc%</th>
      <th width="60"  align="right" style="padding:7px 8px; font-size:9.5px; color:#fff; text-transform:uppercase; letter-spacing:.5px; font-weight:700;">Taxable</th>
      <th width="36"  align="right" style="padding:7px 8px; font-size:9.5px; color:#fff; text-transform:uppercase; letter-spacing:.5px; font-weight:700;">GST%</th>
      <th width="54"  align="right" style="padding:7px 8px; font-size:9.5px; color:#fff; text-transform:uppercase; letter-spacing:.5px; font-weight:700;">Tax</th>
      <th width="62"  align="right" style="padding:7px 8px; font-size:9.5px; color:#fff; text-transform:uppercase; letter-spacing:.5px; font-weight:700;">Total (&#8377;)</th>
    </tr>
  </thead>
  <tbody>
    @foreach($invoice->items as $i => $item)
    @php $bg = ($i % 2 === 1) ? '#f9fafb' : '#ffffff'; @endphp
    <tr style="background:{{ $bg }}; border-bottom:1px solid #eaeaea;">
      <td style="padding:6px 8px; font-size:11px; color:#888;">{{ $i + 1 }}</td>
      <td style="padding:6px 8px; font-size:11px;">
        <strong>{{ $item->item_name }}</strong>
        @if($item->description)
          <div style="font-size:9.5px; color:#777; margin-top:1px;">{{ $item->description }}</div>
        @endif
      </td>
      <td style="padding:6px 8px; font-size:11px;">{{ $item->hsn_sac ?: '&#8212;' }}</td>
      <td align="right" style="padding:6px 8px; font-size:11px;">
        {{ rtrim(rtrim(number_format($item->qty, 3), '0'), '.') }} {{ $item->unit }}
      </td>
      <td align="right" style="padding:6px 8px; font-size:11px;">{{ fmt_inr($item->rate) }}</td>
      <td align="right" style="padding:6px 8px; font-size:11px;">
        {{ $item->discount_percent > 0 ? $item->discount_percent.'%' : '-' }}
      </td>
      <td align="right" style="padding:6px 8px; font-size:11px;">{{ fmt_inr($item->taxable_amount) }}</td>
      <td align="right" style="padding:6px 8px; font-size:11px;">{{ $item->gst_rate }}%</td>
      <td align="right" style="padding:6px 8px; font-size:11px;">{{ fmt_inr($item->total_tax) }}</td>
      <td align="right" style="padding:6px 8px; font-size:11px; font-weight:700;">{{ fmt_inr($item->total_amount) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

{{-- ══════════════════════════════════════════
     AMOUNT IN WORDS  (full width)
═══════════════════════════════════════════ --}}
<div style="background:#fef4e6; padding:6px 10px; font-size:10.5px;
            color:#a35c08; font-style:italic; margin-bottom:12px;">
  {{ number_to_words($invoice->grand_total) }}
</div>

{{-- ══════════════════════════════════════════
     BOTTOM SECTION
     Left : Bank Details + Notes
     Right: Totals table
═══════════════════════════════════════════ --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr>

    {{-- Bank Details + Notes --}}
    <td valign="top" style="padding-right:14px;">
      @if(!empty($settings['bank_name']) || !empty($settings['bank_acc_no']))
        <div style="font-size:9.5px; font-weight:700; text-transform:uppercase;
                    letter-spacing:1px; color:#888; margin-bottom:5px;">Bank Details</div>
        <div style="font-size:11px; color:#444; line-height:1.85;">
          @if(!empty($settings['bank_name']))<strong>{{ $settings['bank_name'] }}</strong>@endif
          @if(!empty($settings['bank_acc_no'])) &nbsp;|&nbsp; A/C: {{ $settings['bank_acc_no'] }}@endif
          @if(!empty($settings['bank_ifsc']) || !empty($settings['bank_branch']))
            <br>
            @if(!empty($settings['bank_ifsc']))IFSC: {{ $settings['bank_ifsc'] }}@endif
            @if(!empty($settings['bank_branch'])) &nbsp;|&nbsp; Branch: {{ $settings['bank_branch'] }}@endif
          @endif
        </div>
      @endif

      @if($invoice->notes)
        <div style="font-size:9.5px; font-weight:700; text-transform:uppercase;
                    letter-spacing:1px; color:#888; margin-top:10px; margin-bottom:4px;">Notes</div>
        <div style="font-size:11px; color:#555; line-height:1.6;">{{ $invoice->notes }}</div>
      @endif
    </td>

    {{-- Totals --}}
    <td width="270" valign="top">
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        <tr>
          <td style="padding:3px 6px; font-size:11px; color:#555;">Subtotal</td>
          <td align="right" style="padding:3px 6px; font-size:11px; font-variant-numeric:tabular-nums;">
            {{ fmt_inr($invoice->sub_total) }}
          </td>
        </tr>
        @if($invoice->discount_amount > 0)
        <tr>
          <td style="padding:3px 6px; font-size:11px; color:#555;">Discount</td>
          <td align="right" style="padding:3px 6px; font-size:11px; color:#0c7a59; font-variant-numeric:tabular-nums;">
            -{{ fmt_inr($invoice->discount_amount) }}
          </td>
        </tr>
        @endif

        {{-- GST Breakdown box --}}
        <tr>
          <td colspan="2" style="padding:4px 0;">
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="border-collapse:collapse; background:#f5f6fb;
                          border:1px solid #e2e5ed;">
              <tr><td colspan="2" style="padding:6px 10px 4px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                  @if($invoice->is_intra_state)
                  <tr>
                    <td style="font-size:11px; padding:2px 0;">CGST</td>
                    <td align="right" style="font-size:11px; padding:2px 0; font-variant-numeric:tabular-nums;">
                      {{ fmt_inr($invoice->total_cgst) }}
                    </td>
                  </tr>
                  <tr>
                    <td style="font-size:11px; padding:2px 0;">SGST</td>
                    <td align="right" style="font-size:11px; padding:2px 0; font-variant-numeric:tabular-nums;">
                      {{ fmt_inr($invoice->total_sgst) }}
                    </td>
                  </tr>
                  @else
                  <tr>
                    <td style="font-size:11px; padding:2px 0;">IGST</td>
                    <td align="right" style="font-size:11px; padding:2px 0; font-variant-numeric:tabular-nums;">
                      {{ fmt_inr($invoice->total_igst) }}
                    </td>
                  </tr>
                  @endif
                  <tr>
                    <td colspan="2" style="padding-top:4px; border-top:1px solid #d8dbe6;"></td>
                  </tr>
                  <tr>
                    <td style="font-size:11px; font-weight:700; padding:2px 0;">Total Tax</td>
                    <td align="right" style="font-size:11px; font-weight:700; padding:2px 0; font-variant-numeric:tabular-nums;">
                      {{ fmt_inr($invoice->total_tax) }}
                    </td>
                  </tr>
                </table>
              </td></tr>
            </table>
          </td>
        </tr>

        @if(abs($invoice->round_off) > 0)
        <tr>
          <td style="padding:3px 6px; font-size:11px; color:#555;">Round Off</td>
          <td align="right" style="padding:3px 6px; font-size:11px; font-variant-numeric:tabular-nums;">
            {{ $invoice->round_off >= 0 ? '+' : '' }}{{ fmt_inr($invoice->round_off) }}
          </td>
        </tr>
        @endif

        {{-- Grand Total --}}
        <tr style="border-top:2px solid #111318;">
          <td style="padding:7px 6px 3px; font-size:14px; font-weight:700; color:#111318;">
            Grand Total
          </td>
          <td align="right" style="padding:7px 6px 3px; font-size:14px; font-weight:700;
                                   color:#111318; font-variant-numeric:tabular-nums;">
            {{ fmt_inr($invoice->grand_total) }}
          </td>
        </tr>

        @if($invoice->payments->count() > 0)
        {{-- Payment rows --}}
        @foreach($invoice->payments as $pmt)
        <tr>
          <td style="padding:3px 6px; font-size:11px; color:#555;">
            Payment ({{ strtoupper($pmt->mode) }}
            @if($pmt->reference) · {{ $pmt->reference }}@endif
            · {{ $pmt->payment_date->format('d M Y') }})
          </td>
          <td align="right" style="padding:3px 6px; font-size:11px; color:#0c7a59; font-variant-numeric:tabular-nums;">
            -{{ fmt_inr($pmt->amount) }}
          </td>
        </tr>
        @endforeach
        @if($invoice->balance_due > 0)
        {{-- Balance Due --}}
        <tr style="border-top:1px solid #dde0e8;">
          <td style="padding:6px 6px 3px; font-size:13px; font-weight:700; color:#d62b2b;">
            Balance Due
          </td>
          <td align="right" style="padding:6px 6px 3px; font-size:13px; font-weight:700;
                                   color:#d62b2b; font-variant-numeric:tabular-nums;">
            {{ fmt_inr($invoice->balance_due) }}
          </td>
        </tr>
        @endif
        @endif
      </table>
    </td>

  </tr>
</table>

{{-- ══════════════════════════════════════════
     SIGNATURE
═══════════════════════════════════════════ --}}
<table width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:collapse; margin-top:16px; border-top:1px solid #dde0e8;">
  <tr>
    <td style="padding-top:10px;">&nbsp;</td>
    <td width="190" align="right" style="padding-top:10px;" valign="bottom">
      <div style="font-size:11px; color:#666;">
        For <strong>{{ $settings['company_name'] ?? '' }}</strong>
      </div>
      <div style="border-top:1px solid #bbb; width:140px; margin:24px 0 4px auto;"></div>
      <div style="font-size:11px; font-weight:700; color:#333;">Authorised Signatory</div>
    </td>
  </tr>
</table>

</body>
</html>
