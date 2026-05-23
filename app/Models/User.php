<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'association_id',
        'member_id',
        'username',
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'phone',
        'is_mfa_enabled',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_mfa_enabled' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot('assigned_by', 'assigned_at');
    }

    public function hasRole(string ...$roleNames): bool
    {
        if (empty($roleNames)) {
            return false;
        }

        return $this->roles()
            ->whereIn('name', $roleNames)
            ->exists();
    }

    public function fullName(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $name !== '' ? $name : $this->username;
    }

    public function getNameAttribute(): string
    {
        return $this->fullName();
    }
}
