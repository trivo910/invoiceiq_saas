<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo as BelongsToRelation;

class ImportRow extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];
    protected $casts = ['payload' => 'array', 'errors' => 'array', 'warnings' => 'array'];

    protected static function booted(): void
    {
        static::saving(function (self $row): void {
            abort_unless(ImportBatch::whereKey($row->import_batch_id)->exists(), 404);
        });
    }

    public function batch(): BelongsToRelation { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }
    public function shouldAuditTenantChanges(): bool { return false; }
}
