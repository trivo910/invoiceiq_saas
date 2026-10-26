<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Validation\ValidationException;

class Payment extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::saving(function (self $payment): void {
            if (! Invoice::whereKey($payment->invoice_id)->exists()) {
                throw ValidationException::withMessages(['invoice_id' => 'The selected invoice is not available in this workspace.']);
            }
        });
    }

    protected $fillable = ['invoice_id', 'amount', 'payment_date', 'mode', 'reference', 'notes'];
    protected $casts = ['payment_date' => 'date', 'amount' => 'float'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
