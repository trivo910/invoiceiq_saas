<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Support\DashboardCache;
use App\Support\TenantContext;

class DashboardController extends Controller
{
    public function index()
    {
        // Collapse 7 separate queries into 2 using conditional aggregation.
        // Cache for 5 minutes — stats don't need to be real-time.
        $tenantId = app(TenantContext::class)->id();
        abort_unless($tenantId, 403);
        $stats = Cache::remember(DashboardCache::key(), 300, function () use ($tenantId) {
            $agg = DB::selectOne("
                SELECT
                    SUM(grand_total)                                                   AS total_invoiced,
                    SUM(CASE WHEN status = 'paid'    THEN grand_total       ELSE 0 END) AS total_paid,
                    SUM(CASE WHEN status IN ('sent','partial') THEN grand_total - amount_paid ELSE 0 END) AS total_outstanding,
                    SUM(CASE WHEN status = 'sent' AND due_date < CURDATE() THEN grand_total ELSE 0 END) AS total_overdue,
                    COUNT(*)                                                            AS invoice_count,
                    SUM(CASE WHEN status = 'draft'  THEN 1         ELSE 0 END) AS draft_count
                FROM invoices
                WHERE tenant_id = ?
            ", [$tenantId]);

            return [
                'total_invoiced'    => $agg->total_invoiced    ?? 0,
                'total_paid'        => $agg->total_paid        ?? 0,
                'total_outstanding' => $agg->total_outstanding ?? 0,
                'total_overdue'     => $agg->total_overdue     ?? 0,
                'invoice_count'     => $agg->invoice_count     ?? 0,
                'draft_count'       => $agg->draft_count       ?? 0,
                'customer_count'    => Customer::count(),
            ];
        });

        $recent = Invoice::with('customer')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $monthly = Invoice::selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') as month, SUM(grand_total) as total")
            ->where('invoice_date', '>=', now()->subMonths(6)->startOfMonth())
            ->whereNotIn('status', ['cancelled'])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('dashboard', compact('stats', 'recent', 'monthly'));
    }
}
