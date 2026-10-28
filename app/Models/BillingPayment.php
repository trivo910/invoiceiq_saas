<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\TenantContext;
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
            $tenantId = app(TenantContext::class)->id();
            if (! $tenantId || ($payment->tenant_id !== null && (int) $payment->tenant_id !== $tenantId)) {
                throw ValidationException::withMessages(['tenant_id' => 'The payment must belong to the active workspace.']);
            }
            if ($payment->subscription_id) {
                $subscription = TenantSubscription::whereKey($payment->subscription_id)->first();
                if (! $subscription || (int) $subscription->tenant_id !== $tenantId) {
                    throw ValidationException::withMessages(['subscription_id' => 'The subscription is not available in this workspace.']);
                }
            }
        });
    }

    public function subscription(): BelongsTo { return $this->belongsTo(TenantSubscription::class, 'subscription_id'); }
}
