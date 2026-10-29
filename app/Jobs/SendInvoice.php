<?php
namespace App\Jobs;

use App\Models\{Invoice, InvoiceDelivery, Setting, User};
use App\Services\{TenantInvoiceMailer, TenantWork};
use App\Support\DashboardCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendInvoice implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 60;
    public function backoff(): array { return [60, 180]; }
    public function __construct(public int $tenantId, public int $deliveryId) {}

    public function handle(TenantInvoiceMailer $mailer): void
    {
        app(TenantWork::class)->run($this->tenantId, function ($tenant) use ($mailer) {
            $delivery = InvoiceDelivery::findOrFail($this->deliveryId);
            if ($delivery->status === 'sent') return;
            $actor = User::find($delivery->requested_by);
            $role = $actor?->tenants()->where('tenants.id', $tenant->id)->value('tenant_user.role');
            if (! in_array($role, ['owner', 'admin', 'accountant', 'staff'], true) || (Setting::get('email_enabled', '0') !== '1')) {
                $delivery->update(['status' => 'failed']);
                return;
            }
            $delivery->increment('attempts');
            // Serialize duplicate worker execution for this delivery; SMTP itself is at-least-once.
            DB::transaction(function () use ($mailer, $delivery) {
                $delivery = InvoiceDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
                if ($delivery->status === 'sent') return;
                $invoice = Invoice::findOrFail($delivery->invoice_id);
                if ($invoice->status === 'cancelled') { $delivery->update(['status' => 'failed']); return; }
                try {
                    $mailer->send($invoice, $delivery->payload);
                } catch (\Throwable $e) {
                    throw new \RuntimeException('Invoice delivery failed; check protected mail configuration.');
                }
                $delivery->update(['status' => 'sent', 'sent_at' => now()]);
                if ($invoice->status === 'draft') $invoice->update(['status' => 'sent']);
                DashboardCache::forget();
            });
        });
    }

    public function failed(?\Throwable $error): void
    {
        try {
            app(TenantWork::class)->run($this->tenantId, function () {
                $delivery = InvoiceDelivery::find($this->deliveryId);
                if ($delivery && $delivery->status !== 'sent') $delivery->update(['status' => 'failed']);
            });
            return;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            // Suspended or soft-deleted workspaces cannot establish normal tenant context.
        }
        // Trusted job IDs still constrain the fallback to this tenant and delivery row.
        DB::table('invoice_deliveries')->where('tenant_id', $this->tenantId)->where('id', $this->deliveryId)
            ->where('status', '!=', 'sent')->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
