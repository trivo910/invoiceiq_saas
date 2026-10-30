<?php

namespace App\Services\Import;

use App\Models\{Customer, ImportBatch, ImportRow, Item, Tenant};
use App\Services\BillingService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class ImportProcessingService
{
    public function process(int $batchId, string $entity, string $mode): array
    {
        if (! in_array($mode, ['import_valid', 'stop_on_issues'], true)) {
            throw new ImportProcessingException('Choose how to handle rows that need attention.', 422);
        }

        $tenantId = app(TenantContext::class)->id();
        if (! $tenantId) throw new ImportProcessingException('Your workspace could not be verified.', 404);

        return DB::transaction(function () use ($tenantId, $batchId, $entity, $mode): array {
            $tenant = Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
            if (! app(BillingService::class)->canWrite($tenant)) {
                throw new ImportProcessingException('This workspace is currently read-only. Restore write access before importing records.', 403);
            }

            $batch = ImportBatch::where('entity', $entity)->lockForUpdate()->findOrFail($batchId);
            if ($batch->status !== 'validated') {
                throw new ImportProcessingException('This file has already been processed or is not ready. Upload it again to start a new import.');
            }

            $rows = ImportRow::where('import_batch_id', $batch->id)->orderBy('row_number')->lockForUpdate()->get();
            if ($rows->isEmpty()) throw new ImportProcessingException('This preview has no rows to import. Upload the file again.');
            $hasIssues = $rows->contains(fn (ImportRow $row) => $row->status !== 'ready');
            if ($mode === 'stop_on_issues' && $hasIssues) {
                throw new ImportProcessingException('No records were added. Fix all errors and review or remove possible duplicates before importing.', 422);
            }

            $imported = 0;
            $skipped = 0;
            foreach ($rows as $row) {
                if ($row->status !== 'ready') {
                    if ($row->status === 'warning') $row->update(['status' => 'skipped']);
                    $skipped++;
                    continue;
                }

                $entity === 'customers'
                    ? Customer::create($this->customerAttributes($row->payload ?? []))
                    : Item::create($this->itemAttributes($row->payload ?? []));
                $row->update(['status' => 'imported']);
                $imported++;
            }

            $batch->forceFill(['status' => 'completed'])->save();

            return compact('imported', 'skipped');
        }, 3);
    }

    private function customerAttributes(array $payload): array
    {
        return [
            'name' => (string) ($payload['customer_name'] ?? ''),
            'gstin' => $payload['gstin'] ?: null,
            'pan' => $payload['pan'] ?: null,
            'email' => $payload['email'] ?: null,
            'phone' => $payload['phone'] ?: null,
            'billing_address' => $payload['billing_address'] ?: null,
            'billing_city' => $payload['billing_city'] ?: null,
            'billing_state' => $payload['billing_state'] ?: null,
            'billing_state_code' => $payload['billing_state_code'] ?: null,
            'billing_pincode' => $payload['billing_pincode'] ?: null,
            'shipping_address' => $payload['shipping_address'] ?: null,
            'shipping_city' => $payload['shipping_city'] ?: null,
            'shipping_state' => $payload['shipping_state'] ?: null,
            'shipping_state_code' => $payload['shipping_state_code'] ?: null,
            'credit_limit' => $payload['credit_limit'] ?: 0,
            'payment_terms' => $payload['payment_terms_days'] ?? 30,
            'notes' => $payload['notes'] ?: null,
            'is_active' => $payload['active'] ?? true,
        ];
    }

    private function itemAttributes(array $payload): array
    {
        return [
            'name' => (string) ($payload['item_name'] ?? ''),
            'code' => $payload['item_code'] ?: null,
            'hsn_sac' => $payload['hsn_sac'] ?: null,
            'description' => $payload['description'] ?: null,
            'type' => $payload['type'],
            'rate' => $payload['selling_price'],
            'gst_rate' => $payload['gst_rate'],
            'unit' => $payload['unit'],
            'is_active' => $payload['active'] ?? true,
        ];
    }
}
