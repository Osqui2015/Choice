<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'current_daily_streak', 'max_daily_streak', 'last_activity_date',
        'current_correct_streak', 'max_correct_streak',
    ];

    protected $casts = [
        'last_activity_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
