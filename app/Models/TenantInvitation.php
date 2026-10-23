<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInvitation extends Model
{
    protected $fillable = ['tenant_id', 'invited_by', 'email', 'role', 'token_hash', 'expires_at', 'accepted_at'];

    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
