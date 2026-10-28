<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InvoiceDelivery extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    protected $attributes = ['status' => 'queued', 'attempts' => 0];
    protected $hidden = ['payload'];
    protected $casts = ['payload' => 'encrypted:array', 'sent_at' => 'datetime', 'attempts' => 'integer'];
    protected static function booted(): void
    {
        static::saving(fn (self $delivery) => app(\App\Services\WorkspaceWorkflow::class)->validateInvoiceRecord($delivery));
    }
}
