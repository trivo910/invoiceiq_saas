<?php
namespace App\Http\Controllers;

use App\Http\Requests\BrandingRequest;
use App\Jobs\BuildAccountExport;
use App\Models\{AccountExport, Invoice, InvoiceDelivery, InvoiceShareLink, Setting};
use App\Services\{InvoicePdf, TenantWork, WorkspaceExport, WorkspaceWorkflow};
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class WorkspaceWorkflowController extends Controller
{
    public function activity(Invoice $invoice)
    {
        return view('workflows.invoice', ['invoice' => $invoice,
            'links' => InvoiceShareLink::where('invoice_id', $invoice->id)->latest('id')->get(),
            'deliveries' => InvoiceDelivery::where('invoice_id', $invoice->id)->latest('id')->limit(50)->get()]);
    }

    public function share(Request $request, Invoice $invoice, WorkspaceWorkflow $workflow)
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:90']]);
        $url = $workflow->share($invoice, $request->user()->id, $data['days']);
        return back()->with('invoice_share_url', $url)->with('success', 'Invoice link created. Copy it now; it will not be shown again.');
    }

    public function revoke(Invoice $invoice, InvoiceShareLink $link)
    {
        abort_unless($link->invoice_id === $invoice->id, 404);
        $link->update(['revoked_at' => now()]);
        return back()->with('success', 'Invoice link revoked.');
    }

    public function publicInvoice(string $token, InvoicePdf $pdf)
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        // Capability lookup: only the hash of the unguessable bearer token resolves tenant context.
        $row = DB::table('invoice_share_links')->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($row, 404);
        $tenant = \App\Models\Tenant::find($row->tenant_id);
        abort_unless($tenant?->status === 'active', 404);
        return app(TenantWork::class)->run($tenant->id, function () use ($row, $pdf) {
            $invoice = Invoice::whereKey($row->invoice_id)->whereNotIn('status', ['draft', 'cancelled'])->firstOrFail();
            return $pdf->make($invoice)->stream('Invoice.pdf', ['Attachment' => false])->withHeaders([
                'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow', 'X-Content-Type-Options' => 'nosniff',
            ]);
        });
    }

    public function branding(BrandingRequest $request)
    {
        foreach ($request->validated() as $key => $value) Setting::set($key, $value);
        return back()->with('success', 'Invoice branding saved.');
    }

    public function exports()
    {
        $exports = request()->user()->hasRole('owner') ? AccountExport::latest('id')->limit(20)->get() : collect();
        return view('workflows.exports', compact('exports'));
    }

    public function csv(string $resource, WorkspaceExport $writer)
    {
        abort_unless(in_array($resource, WorkspaceExport::RESOURCES, true), 404);
        $tenantId = app(TenantContext::class)->id();
        return response()->streamDownload(function () use ($resource, $writer, $tenantId) {
            app(TenantWork::class)->run($tenantId, function () use ($resource, $writer) {
                $stream = fopen('php://output', 'w');
                try { $writer->writeCsv($resource, $stream); } finally { fclose($stream); }
            });
        }, $resource.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function requestExport(Request $request)
    {
        $tenantId = app(TenantContext::class)->id();
        DB::transaction(function () use ($request, $tenantId) {
            \App\Models\Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
            abort_if(AccountExport::where('status', 'queued')->exists(), 409, 'An export is already queued for this workspace.');
            $export = AccountExport::create(['requested_by' => $request->user()->id]);
            BuildAccountExport::dispatch($tenantId, $export->id)->onQueue('default')->afterCommit();
        });
        return back()->with('success', 'Workspace export requested. Refresh this page for its status.');
    }

    public function download(AccountExport $export)
    {
        $prefix = 'tenants/'.app(TenantContext::class)->id().'/exports/';
        abort_unless($export->status === 'ready' && $export->expires_at?->isFuture()
            && is_string($export->path) && preg_match('~^'.preg_quote($prefix, '~').'[a-f0-9-]+\.zip$~D', $export->path), 404);
        abort_unless(Storage::disk('tenant_private')->exists($export->path), 404);
        return Storage::disk('tenant_private')->download($export->path, 'workspace-export.zip', ['Cache-Control' => 'private, no-store']);
    }
}
