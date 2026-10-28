<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit records are append-only.'));
    }
}
