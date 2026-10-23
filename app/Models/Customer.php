<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToTenant;

class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name', 'gstin', 'pan', 'email', 'phone',
        'billing_address', 'billing_city', 'billing_state', 'billing_state_code', 'billing_pincode',
        'shipping_address', 'shipping_city', 'shipping_state', 'shipping_state_code',
        'credit_limit', 'payment_terms', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credit_limit' => 'integer',
        'payment_terms' => 'integer',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function setGstinAttribute(?string $value): void
    {
        $normalized = strtoupper(trim((string) $value));
        $this->attributes['gstin'] = $normalized === '' ? null : $normalized;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('gstin', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    public function getTotalInvoicedAttribute(): float
    {
        return $this->invoices()->sum('grand_total');
    }

    public function getTotalOutstandingAttribute(): float
    {
        return $this->invoices()
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->selectRaw('SUM(grand_total - amount_paid) as outstanding')
            ->value('outstanding') ?? 0;
    }
}
