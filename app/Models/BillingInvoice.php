<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class BillingInvoice extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id'];
    protected $casts = [
        'subtotal_paise' => 'integer', 'tax_paise' => 'integer', 'total_paise' => 'integer',
        'seller_snapshot' => 'array', 'buyer_snapshot' => 'array', 'tax_breakdown' => 'array', 'issued_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $invoice): void {
            $tenantId = app(TenantContext::class)->id();
            if (! $tenantId || ($invoice->tenant_id !== null && (int) $invoice->tenant_id !== $tenantId)) {
                throw ValidationException::withMessages(['tenant_id' => 'The invoice must belong to the active workspace.']);
            }
            if ($invoice->payment_id) {
                $payment = BillingPayment::whereKey($invoice->payment_id)->first();
                if (! $payment || (int) $payment->tenant_id !== $tenantId) {
                    throw ValidationException::withMessages(['payment_id' => 'The payment is not available in this workspace.']);
                }
            }
        });
    }

    public function payment(): BelongsTo { return $this->belongsTo(BillingPayment::class, 'payment_id'); }
}
