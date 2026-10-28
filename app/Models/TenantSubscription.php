<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class TenantSubscription extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'trial_ends_at' => 'datetime', 'current_period_starts_at' => 'datetime',
        'current_period_ends_at' => 'datetime', 'grace_ends_at' => 'datetime', 'past_due_started_at' => 'datetime',
        'cancel_at_period_end' => 'datetime', 'ended_at' => 'datetime',
        'provider_state_at' => 'datetime', 'provider_metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $subscription): void {
            $tenantId = app(\App\Support\TenantContext::class)->id();
            $hasForeignTenant = $subscription->tenant_id !== null
                && (int) $subscription->tenant_id !== $tenantId;
            $missingTenantOnUpdate = $subscription->exists && $subscription->tenant_id === null;
            $tenantChangedAfterCreation = $subscription->exists && $subscription->isDirty('tenant_id');
            if (! $tenantId || $hasForeignTenant || $missingTenantOnUpdate || $tenantChangedAfterCreation) {
                throw ValidationException::withMessages(['tenant_id' => 'The subscription must belong to the active workspace.']);
            }
        });
    }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function payments(): HasMany { return $this->hasMany(BillingPayment::class, 'subscription_id'); }
}
