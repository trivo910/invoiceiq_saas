<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class BillingPayment extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id'];
    protected $casts = ['amount_paise' => 'integer', 'paid_at' => 'datetime', 'provider_metadata' => 'array'];

    protected static function booted(): void
    {
        static::saving(function (self $payment): void {
            if ($payment->subscription_id && ! TenantSubscription::whereKey($payment->subscription_id)->exists()) {
                throw ValidationException::withMessages(['subscription_id' => 'The subscription is not available in this workspace.']);
            }
        });
    }

    public function subscription(): BelongsTo { return $this->belongsTo(TenantSubscription::class, 'subscription_id'); }
}
