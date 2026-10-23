<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToTenant;

class Payment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['invoice_id', 'amount', 'payment_date', 'mode', 'reference', 'notes'];
    protected $casts = ['payment_date' => 'date', 'amount' => 'float'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
