<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'duration_days',
        'max_specialties',     // null = ilimitadas (Full)
        'includes_flashcards',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price'              => 'integer',
        'duration_days'      => 'integer',
        'max_specialties'    => 'integer',
        'includes_flashcards' => 'boolean',
        'is_active'          => 'boolean',
        'sort_order'         => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /** Plan Full: acceso a todas las especialidades sin elegir. */
    public function isUnlimited(): bool
    {
        return $this->max_specialties === null;
    }

    /** Precio formateado (ej: "$ 4.990"). */
    public function formattedPrice(): string
    {
        return '$ ' . number_format($this->price, 0, ',', '.');
    }
}
