<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['value' => 'integer', 'is_active' => 'boolean', 'starts_at' => 'datetime', 'expires_at' => 'datetime'];
}
