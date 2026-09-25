<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Full access: catalog/pricing/import management, user management, sees
     * every quote.
     */
    public const ROLE_ADMIN = 'admin';

    /**
     * Creates quotes; can only see/print quotes they themselves created. No
     * access to any /admin/* screen.
     */
    public const ROLE_SALES = 'sales';

    /**
     * Sees every quote (read-only) same as Admin, but no access to
     * catalog/pricing/import/user management.
     */
    public const ROLE_MANAGER = 'manager';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_SALES, self::ROLE_MANAGER];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSales(): bool
    {
        return $this->role === self::ROLE_SALES;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /**
     * Admin and Manager both see every quote; Sales only sees quotes they
     * created themselves.
     */
    public function canViewAllQuotes(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }
}
