<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialty extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'slug', 'icon', 'color', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class);
    }
}
