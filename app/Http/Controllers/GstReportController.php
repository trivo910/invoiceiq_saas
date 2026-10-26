<?php

namespace App\Http\Controllers;

use App\Exports\GstReportExport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Support\TenantContext;

class GstReportController extends Controller
{
    private const REPORT_LABELS = [
        'gstr1'         => 'GSTR-1 Summary',
        'b2b'           => 'B2B — Registered Customers',
        'b2c'           => 'B2C — Unregistered Customers',
        'hsn'           => 'HSN / SAC Wise Summary',
        'tax_liability' => 'Tax Liability Report',
    ];

    public function index(Request $request)
    {
        $report = $request->report ?? 'gstr1';
        $from   = $request->from   ?? now()->startOfMonth()->format('Y-m-d');
        $to     = $request->to     ?? now()->endOfMonth()->format('Y-m-d');
        $period = $request->period ?? 'this_month';

        [$from, $to] = $this->resolvePeriod($period, $from, $to);

        $data = $this->buildData($report, $from, $to);

        return view('reports.gst', compact('report', 'from', 'to', 'period', 'data'));
    }

    public function export(Request $request)
    {
        $report = $request->report ?? 'gstr1';
        $from   = $request->from   ?? now()->startOfMonth()->format('Y-m-d');
        $to     = $request->to     ?? now()->endOfMonth()->format('Y-m-d');
        $period = $request->period ?? 'custom';
        $format = $request->format ?? 'pdf';

        [$from, $to] = $this->resolvePeriod($period, $from, $to);

        $data = $this->buildData($report, $from, $to);

        $label    = self::REPORT_LABELS[$report] ?? 'GST Report';
        $filename = str_replace(['/', '\\'], '-', $label) . ' ' . $from . ' to ' . $to;

        if ($format === 'excel') {
            return Excel::download(
                new GstReportExport($report, $data, $from, $to),
                $filename . '.xlsx'
            );
        }

        $settings      = Setting::pluck('value', 'key')->toArray();
        $reportLabels  = self::REPORT_LABELS;
        $logoBase64    = \App\Http\Controllers\SettingsController::logoBase64($settings);

        $pdf = Pdf::loadView('reports.gst_pdf', compact(
            'report', 'data', 'from', 'to', 'settings', 'reportLabels', 'logoBase64'
        ))->setPaper('a4', 'landscape');

        return $pdf->download($filename . '.pdf');
    }

    /* ── Data builder ────────────────────────────────────── */
    private function buildData(string $report, string $from, string $to): array
    {
        return match ($report) {
            'gstr1'         => $this->gstr1($from, $to),
            'hsn'           => $this->hsnSummary($from, $to),
            'tax_liability' => $this->taxLiability($from, $to),
            'b2b'           => $this->b2bSummary($from, $to),
            'b2c'           => $this->b2cSummary($from, $to),
            default         => $this->gstr1($from, $to),
        };
    }

    /* ── GSTR-1 Summary ──────────────────────────────────── */
    private function gstr1(string $from, string $to): array
    {
        $invoices = Invoice::with('items')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('invoice_date', [$from, $to])
            ->orderBy('invoice_date')
            ->get();

        $b2b   = [];
        $b2c   = [];
        $totals = [
            'taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0,
            'total_tax' => 0, 'grand' => 0, 'invoices' => 0,
        ];

        foreach ($invoices as $inv) {
            $totals['invoices']++;
            $totals['taxable']   += $inv->sub_total - $inv->discount_amount;
            $totals['cgst']      += $inv->total_cgst;
            $totals['sgst']      += $inv->total_sgst;
            $totals['igst']      += $inv->total_igst;
            $totals['total_tax'] += $inv->total_tax;
            $totals['grand']     += $inv->grand_total;

            $row = [
                'id'       => $inv->id,
                'number'   => $inv->number,
                'date'     => $inv->invoice_date->format('d M Y'),
                'customer' => $inv->customer_name,
                'gstin'    => $inv->customer_gstin,
                'state'    => $inv->customer_state,
                'is_intra' => $inv->is_intra_state,
                'taxable'  => $inv->sub_total - $inv->discount_amount,
                'cgst'     => $inv->total_cgst,
                'sgst'     => $inv->total_sgst,
                'igst'     => $inv->total_igst,
                'total_tax'=> $inv->total_tax,
                'grand'    => $inv->grand_total,
                'status'   => $inv->status,
            ];

            if ($inv->customer_gstin) {
                $b2b[] = $row;
            } else {
                $b2c[] = $row;
            }
        }

        return compact('b2b', 'b2c', 'totals');
    }

    /* ── HSN-wise Summary ────────────────────────────────── */
    private function hsnSummary(string $from, string $to): array
    {
        $rows = InvoiceItem::join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoice_items.tenant_id', app(TenantContext::class)->id())
            ->whereColumn('invoices.tenant_id', 'invoice_items.tenant_id')
            ->whereNotIn('invoices.status', ['draft', 'cancelled'])
            ->whereBetween('invoices.invoice_date', [$from, $to])
            ->select(
                'invoice_items.hsn_sac',
                'invoice_items.unit',
                'invoice_items.gst_rate',
                DB::raw('SUM(invoice_items.qty)              as total_qty'),
                DB::raw('SUM(invoice_items.taxable_amount)   as total_taxable'),
                DB::raw('SUM(invoice_items.cgst_amount)      as total_cgst'),
                DB::raw('SUM(invoice_items.sgst_amount)      as total_sgst'),
                DB::raw('SUM(invoice_items.igst_amount)      as total_igst'),
                DB::raw('SUM(invoice_items.total_tax)        as total_tax'),
                DB::raw('SUM(invoice_items.total_amount)     as total_amount'),
                DB::raw('COUNT(DISTINCT invoice_items.invoice_id) as inv_count')
            )
            ->groupBy('invoice_items.hsn_sac', 'invoice_items.unit', 'invoice_items.gst_rate')
            ->orderByDesc('total_taxable')
            ->get();

        $totals = [
            'taxable'   => $rows->sum('total_taxable'),
            'cgst'      => $rows->sum('total_cgst'),
            'sgst'      => $rows->sum('total_sgst'),
            'igst'      => $rows->sum('total_igst'),
            'total_tax' => $rows->sum('total_tax'),
            'grand'     => $rows->sum('total_amount'),
        ];

        return compact('rows', 'totals');
    }

    /* ── Tax Liability Report ────────────────────────────── */
    private function taxLiability(string $from, string $to): array
    {
        $byRate = InvoiceItem::join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoice_items.tenant_id', app(TenantContext::class)->id())
            ->whereColumn('invoices.tenant_id', 'invoice_items.tenant_id')
            ->whereNotIn('invoices.status', ['draft', 'cancelled'])
            ->whereBetween('invoices.invoice_date', [$from, $to])
            ->select(
                'invoice_items.gst_rate',
                DB::raw('SUM(invoice_items.taxable_amount) as taxable'),
                DB::raw('SUM(invoice_items.cgst_amount)    as cgst'),
                DB::raw('SUM(invoice_items.sgst_amount)    as sgst'),
                DB::raw('SUM(invoice_items.igst_amount)    as igst'),
                DB::raw('SUM(invoice_items.total_tax)      as total_tax')
            )
            ->groupBy('invoice_items.gst_rate')
            ->orderBy('invoice_items.gst_rate')
            ->get();

        $monthly = Invoice::whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('invoice_date', [$from, $to])
            ->select(
                DB::raw("DATE_FORMAT(invoice_date, '%b %Y') as month_label"),
                DB::raw("DATE_FORMAT(invoice_date, '%Y-%m') as month_sort"),
                DB::raw('SUM(sub_total - discount_amount) as taxable'),
                DB::raw('SUM(total_cgst)  as cgst'),
                DB::raw('SUM(total_sgst)  as sgst'),
                DB::raw('SUM(total_igst)  as igst'),
                DB::raw('SUM(total_tax)   as total_tax'),
                DB::raw('SUM(grand_total) as grand'),
                DB::raw('COUNT(*)         as inv_count')
            )
            ->groupBy('month_label', 'month_sort')
            ->orderBy('month_sort')
            ->get();

        $totals = [
            'taxable'   => $byRate->sum('taxable'),
            'cgst'      => $byRate->sum('cgst'),
            'sgst'      => $byRate->sum('sgst'),
            'igst'      => $byRate->sum('igst'),
            'total_tax' => $byRate->sum('total_tax'),
        ];

        return compact('byRate', 'monthly', 'totals');
    }

    /* ── B2B Summary ─────────────────────────────────────── */
    private function b2bSummary(string $from, string $to): array
    {
        $rows = Invoice::whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('invoice_date', [$from, $to])
            ->whereNotNull('customer_gstin')
            ->where('customer_gstin', '!=', '')
            ->select(
                'customer_gstin',
                'customer_name',
                'customer_state',
                DB::raw('COUNT(*)                           as inv_count'),
                DB::raw('SUM(sub_total - discount_amount)   as taxable'),
                DB::raw('SUM(total_cgst)                    as cgst'),
                DB::raw('SUM(total_sgst)                    as sgst'),
                DB::raw('SUM(total_igst)                    as igst'),
                DB::raw('SUM(total_tax)                     as total_tax'),
                DB::raw('SUM(grand_total)                   as grand')
            )
            ->groupBy('customer_gstin', 'customer_name', 'customer_state')
            ->orderByDesc('grand')
            ->get();

        $totals = [
            'invoices'  => $rows->sum('inv_count'),
            'taxable'   => $rows->sum('taxable'),
            'cgst'      => $rows->sum('cgst'),
            'sgst'      => $rows->sum('sgst'),
            'igst'      => $rows->sum('igst'),
            'total_tax' => $rows->sum('total_tax'),
            'grand'     => $rows->sum('grand'),
        ];

        return compact('rows', 'totals');
    }

    /* ── B2C Summary ─────────────────────────────────────── */
    private function b2cSummary(string $from, string $to): array
    {
        $rows = Invoice::whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('invoice_date', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('customer_gstin')->orWhere('customer_gstin', '');
            })
            ->select(
                'customer_state',
                'is_intra_state',
                DB::raw('COUNT(*)                           as inv_count'),
                DB::raw('SUM(sub_total - discount_amount)   as taxable'),
                DB::raw('SUM(total_cgst)                    as cgst'),
                DB::raw('SUM(total_sgst)                    as sgst'),
                DB::raw('SUM(total_igst)                    as igst'),
                DB::raw('SUM(total_tax)                     as total_tax'),
                DB::raw('SUM(grand_total)                   as grand')
            )
            ->groupBy('customer_state', 'is_intra_state')
            ->orderByDesc('grand')
            ->get();

        $totals = [
            'invoices'  => $rows->sum('inv_count'),
            'taxable'   => $rows->sum('taxable'),
            'cgst'      => $rows->sum('cgst'),
            'sgst'      => $rows->sum('sgst'),
            'igst'      => $rows->sum('igst'),
            'total_tax' => $rows->sum('total_tax'),
            'grand'     => $rows->sum('grand'),
        ];

        return compact('rows', 'totals');
    }

    /* ── Period resolver ─────────────────────────────────── */
    private function resolvePeriod(string $period, string $from, string $to): array
    {
        return match ($period) {
            'this_month'   => [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')],
            'last_month'   => [now()->subMonth()->startOfMonth()->format('Y-m-d'), now()->subMonth()->endOfMonth()->format('Y-m-d')],
            'this_quarter' => [$this->quarterStart(now()), $this->quarterEnd(now())],
            'last_quarter' => [$this->quarterStart(now()->subQuarter()), $this->quarterEnd(now()->subQuarter())],
            'this_year'    => [now()->startOfYear()->format('Y-m-d'), now()->endOfYear()->format('Y-m-d')],
            'last_year'    => [now()->subYear()->startOfYear()->format('Y-m-d'), now()->subYear()->endOfYear()->format('Y-m-d')],
            default        => [$from, $to],
        };
    }

    private function quarterStart($dt): string
    {
        $m = ceil($dt->month / 3) * 3 - 2;
        return $dt->copy()->month($m)->startOfMonth()->format('Y-m-d');
    }

    private function quarterEnd($dt): string
    {
        $m = ceil($dt->month / 3) * 3;
        return $dt->copy()->month($m)->endOfMonth()->format('Y-m-d');
    }
}
