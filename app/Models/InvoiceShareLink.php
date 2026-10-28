<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InvoiceShareLink extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    protected $hidden = ['token_hash'];
    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    protected static function booted(): void
    {
        static::saving(fn (self $link) => app(\App\Services\WorkspaceWorkflow::class)->validateInvoiceRecord($link));
    }
}
