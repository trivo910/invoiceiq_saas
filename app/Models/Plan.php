<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'monthly_price_paise' => 'integer', 'yearly_price_paise' => 'integer',
        'monthly_invoice_limit' => 'integer', 'member_limit' => 'integer',
        'gst_reports' => 'boolean', 'custom_branding' => 'boolean',
        'api_access' => 'boolean', 'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }
}
