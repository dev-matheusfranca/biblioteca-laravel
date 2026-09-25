<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property UserRole $role
 * @property bool $is_active
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isStaff(): bool
    {
        return $this->isActive() && in_array($this->role, [UserRole::Librarian, UserRole::Admin], true);
    }

    public function isAdmin(): bool
    {
        return $this->isActive() && $this->role === UserRole::Admin;
    }

    /** @return HasMany<Locacao, $this> */
    public function locacoes(): HasMany
    {
        return $this->hasMany(Locacao::class, 'usuario_id');
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'usuario_id');
    }

    /** @return HasMany<PortalNotice, $this> */
    public function avisos(): HasMany
    {
        return $this->hasMany(PortalNotice::class, 'usuario_id');
    }

    /** @return HasMany<ApiIdempotencyRecord, $this> */
    public function apiIdempotencyRecords(): HasMany
    {
        return $this->hasMany(ApiIdempotencyRecord::class, 'usuario_id');
    }
}
