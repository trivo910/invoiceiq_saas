<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class SuperAdmin extends Authenticatable
{
    protected $attributes = ['is_active' => true];
    protected $fillable = ['name', 'email', 'password', 'is_active'];
    protected $hidden = ['password'];
    protected $casts = ['password' => 'hashed', 'is_active' => 'boolean'];
}
