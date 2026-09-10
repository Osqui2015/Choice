<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'accepts_promotions',
        'password',
        'is_premium',
        'premium_until',
        'is_active',
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
            'premium_until' => 'datetime',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'accepts_promotions' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }

    public function quota(): HasOne
    {
        return $this->hasOne(UserDailyQuota::class);
    }

    public function streak(): HasOne
    {
        return $this->hasOne(UserStreak::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Suscripción activa del usuario (no vencida, status=active).
     * Devuelve la más reciente si hay varias.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latestOfMany('expires_at');
    }

    /**
     * Última suscripción del usuario, sea cual sea su estado.
     * Útil para mostrar info aunque esté vencida/cancelada.
     */
    public function latestSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)->latestOfMany();
    }
}
