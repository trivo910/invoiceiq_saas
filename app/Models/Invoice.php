<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToTenant;
use App\Services\InvoiceNumberingService;
use Illuminate\Validation\ValidationException;

class Invoice extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::saving(function (self $invoice): void {
            if ($invoice->customer_id && ! Customer::whereKey($invoice->customer_id)->exists()) {
                throw ValidationException::withMessages(['customer_id' => 'The selected customer is not available in this workspace.']);
            }
        });
    }

    protected $fillable = [
        'number', 'status', 'invoice_date', 'due_date',
        'customer_id', 'customer_name', 'customer_gstin',
        'customer_billing_address', 'customer_city', 'customer_state', 'customer_state_code',
        'place_of_supply', 'place_of_supply_code', 'is_intra_state',
        'discount_type', 'discount_value', 'discount_amount',
        'sub_total', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax',
        'round_off', 'grand_total', 'amount_paid',
        'visible_columns', 'notes', 'terms', 'shipping_address',
    ];

    protected $casts = [
        'invoice_date'    => 'date',
        'due_date'        => 'date',
        'is_intra_state'  => 'boolean',
        'visible_columns' => 'array',
        'sub_total'       => 'float',
        'discount_value'  => 'float',
        'discount_amount' => 'float',
        'total_cgst'      => 'float',
        'total_sgst'      => 'float',
        'total_igst'      => 'float',
        'total_tax'       => 'float',
        'round_off'       => 'float',
        'grand_total'     => 'float',
        'amount_paid'     => 'float',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('payment_date');
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0, $this->grand_total - $this->amount_paid);
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === 'sent' && $this->due_date && $this->due_date->isPast()) {
            return 'overdue';
        }
        return $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->effective_status) {
            'draft'     => 'gray',
            'sent'      => 'blue',
            'paid'      => 'green',
            'partial'   => 'orange',
            'overdue'   => 'red',
            'cancelled' => 'gray',
            default     => 'gray',
        };
    }

    public function scopeStatus($query, string $status)
    {
        if ($status === 'overdue') {
            return $query->where('status', 'sent')
                         ->where('due_date', '<', now()->toDateString());
        }
        return $query->where('status', $status);
    }

    // Preview the next invoice number without consuming the sequence
    public static function peekNumber(?string $invoiceDate = null): string
    {
        return app(InvoiceNumberingService::class)->peek($invoiceDate);
    }

    // Atomically claim and advance the sequence — MUST be called inside DB::transaction()
    public static function nextNumber(?string $invoiceDate = null): string
    {
        return app(InvoiceNumberingService::class)->next($invoiceDate);
    }
}
