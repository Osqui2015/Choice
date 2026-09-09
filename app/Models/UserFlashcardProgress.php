<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFlashcardProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'flashcard_id', 'knew_it', 'review_count', 'last_seen_at',
    ];

    protected $casts = [
        'knew_it' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flashcard(): BelongsTo
    {
        return $this->belongsTo(Flashcard::class);
    }
}
