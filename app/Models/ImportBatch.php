<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ImportBatch extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];
    protected $casts = ['headers' => 'array', 'header_map' => 'array'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function rows(): HasMany { return $this->hasMany(ImportRow::class); }

    public function shouldAuditTenantChanges(): bool { return false; }
}
