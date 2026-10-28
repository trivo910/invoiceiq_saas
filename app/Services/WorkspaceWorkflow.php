<?php
namespace App\Services;

use App\Jobs\SendInvoice;
use App\Models\{Invoice, InvoiceDelivery, InvoiceShareLink, Setting};
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkspaceWorkflow
{
    public function validateInvoiceRecord(Model $record): void
    {
        $id = app(TenantContext::class)->id();
        if (! $id || ($record->exists && $record->isDirty('tenant_id')) || ($record->tenant_id && (int) $record->tenant_id !== $id)
            || ! Invoice::whereKey($record->invoice_id)->exists()) {
            throw ValidationException::withMessages(['invoice_id' => 'The invoice must belong to the active workspace.']);
        }
        $actorId = $record->created_by ?? $record->requested_by;
        $actorField = $record instanceof InvoiceShareLink ? 'created_by' : 'requested_by';
        if ((! $record->exists || $record->isDirty($actorField)) && ! app(TenantContext::class)->current()->users()->whereKey($actorId)->exists()) {
            throw ValidationException::withMessages(['requested_by' => 'The requester must belong to this workspace.']);
        }
    }
    public function share(Invoice $invoice, int $actorId, int $days): string
    {
        abort_unless((int) $invoice->tenant_id === app(TenantContext::class)->id(), 404);
        abort_if(in_array($invoice->status, ['draft', 'cancelled']), 422, 'Only finalized invoices can be shared.');
        $token = bin2hex(random_bytes(32));
        InvoiceShareLink::create(['invoice_id' => $invoice->id, 'created_by' => $actorId, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays($days)]);
        return route('invoices.public', ['token' => $token]);
    }
    public function queueEmail(Invoice $invoice, array $data, int $actorId): InvoiceDelivery
    {
        $settings = Setting::all_settings();
        abort_unless(($settings['email_enabled'] ?? '0') === '1', 422, 'Email sending is disabled. Enable it in Settings.');
        abort_if($invoice->status === 'cancelled', 422, 'Cancelled invoices cannot be emailed.');
        $data = validator($data, [
            'to_email' => ['required', 'email', 'max:255'], 'cc_email' => ['nullable', 'email', 'max:255'],
            'email_subject' => ['required', 'string', 'max:500'], 'email_body' => ['required', 'string', 'max:50000'],
        ])->validate();
        $data['email_subject'] = str_replace(["\r", "\n"], ' ', $data['email_subject']);
        return DB::transaction(function () use ($invoice, $data, $actorId) {
            $delivery = InvoiceDelivery::create(['invoice_id' => $invoice->id, 'requested_by' => $actorId, 'payload' => $data]);
            SendInvoice::dispatch((int) $invoice->tenant_id, $delivery->id)->onQueue('default')->afterCommit();
            return $delivery;
        });
    }
}
