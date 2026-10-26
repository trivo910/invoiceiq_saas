<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'gstin', 'pan', 'state_code', 'address', 'logo_path',
        'currency', 'financial_year_start_month', 'financial_year_start_day',
        'invoice_prefix', 'status', 'trial_ends_at',
        'onboarding_completed_at',
    ];

    protected $casts = [
        'financial_year_start_month' => 'integer',
        'financial_year_start_day' => 'integer',
        'trial_ends_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function currentSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TenantSubscription::class)->latestOfMany();
    }
}
