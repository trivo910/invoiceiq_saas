<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToTenant;

class InvoiceItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'invoice_id', 'item_id', 'sort_order',
        'item_name', 'hsn_sac', 'description', 'unit',
        'qty', 'rate', 'discount_percent', 'discount_amount', 'taxable_amount',
        'gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate', 'cess_rate',
        'cgst_amount', 'sgst_amount', 'igst_amount', 'cess_amount',
        'total_tax', 'total_amount',
    ];

    protected $casts = [
        'qty'              => 'float',
        'rate'             => 'float',
        'discount_percent' => 'float',
        'discount_amount'  => 'float',
        'taxable_amount'   => 'float',
        'gst_rate'         => 'float',
        'cgst_rate'        => 'float',
        'sgst_rate'        => 'float',
        'igst_rate'        => 'float',
        'cess_rate'        => 'float',
        'cgst_amount'      => 'float',
        'sgst_amount'      => 'float',
        'igst_amount'      => 'float',
        'cess_amount'      => 'float',
        'total_tax'        => 'float',
        'total_amount'     => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
