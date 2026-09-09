<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'started_at',
        'requested_at',
        'expires_at',
        'approved_at',
        'status',
        'payment_provider',
        'payment_reference',
        'amount_paid',
        'currency',
        'activated_by_user_id',
        'approved_by_user_id',
        'cancelled_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'requested_at' => 'datetime',
        'expires_at'   => 'datetime',
        'approved_at'  => 'datetime',
        'cancelled_at' => 'datetime',
        'amount_paid'  => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function subscriptionSpecialties(): HasMany
    {
        return $this->hasMany(SubscriptionSpecialty::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(
            Specialty::class,
            'subscription_specialties',
            'user_subscription_id',
            'specialty_id'
        )->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function daysRemaining(): int
    {
        if (! $this->expires_at) {
            return 0;
        }
        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }
}
