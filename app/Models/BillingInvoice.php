<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
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
            if ($invoice->payment_id && ! BillingPayment::whereKey($invoice->payment_id)->exists()) {
                throw ValidationException::withMessages(['payment_id' => 'The payment is not available in this workspace.']);
            }
        });
    }

    public function payment(): BelongsTo { return $this->belongsTo(BillingPayment::class, 'payment_id'); }
}
