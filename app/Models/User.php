<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Tenant;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    const ROLES = [
        'admin'  => 'Admin',
        'staff'  => 'Staff',
        'viewer' => 'Viewer',
    ];

    /** Full access — user management, settings, all CRUD */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Create/edit/delete invoices, customers, items — no user management or settings */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /** Read-only access */
    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    /** Check if the user has any of the given roles */
    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles);
    }

    /** Can create/edit/delete business records */
    public function canWrite(): bool
    {
        return $this->hasRole(['admin', 'staff']);
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function getRoleColorAttribute(): string
    {
        return match ($this->role) {
            'admin'  => 'badge-blue',
            'staff'  => 'badge-green',
            'viewer' => 'badge-gray',
            default  => 'badge-gray',
        };
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot('role')->withTimestamps();
    }
}
