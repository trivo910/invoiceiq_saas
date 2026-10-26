<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSubscription extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'trial_ends_at' => 'datetime', 'current_period_starts_at' => 'datetime',
        'current_period_ends_at' => 'datetime', 'grace_ends_at' => 'datetime',
        'cancel_at_period_end' => 'datetime', 'ended_at' => 'datetime',
        'provider_state_at' => 'datetime', 'provider_metadata' => 'array',
    ];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function payments(): HasMany { return $this->hasMany(BillingPayment::class, 'subscription_id'); }
}
