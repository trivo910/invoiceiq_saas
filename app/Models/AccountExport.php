<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AccountExport extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id', 'tenant_id'];
    protected $attributes = ['status' => 'queued'];
    protected $casts = ['expires_at' => 'datetime'];
    protected static function booted(): void
    {
        static::saving(function (self $export) {
            $id = app(\App\Support\TenantContext::class)->id();
            if (! $id || ($export->exists && $export->isDirty('tenant_id')) || ($export->tenant_id && (int) $export->tenant_id !== $id)) {
                throw ValidationException::withMessages(['tenant_id' => 'The export must belong to the active workspace.']);
            }
            if ($export->exists && $export->isDirty('requested_by')) throw ValidationException::withMessages(['requested_by' => 'The export requester cannot be changed.']);
            if (! $export->exists && ! app(\App\Support\TenantContext::class)->current()->users()->whereKey($export->requested_by)->wherePivot('role', 'owner')->exists()) {
                throw ValidationException::withMessages(['requested_by' => 'A workspace owner must request the export.']);
            }
        });
    }
}
