<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'questions_answered_today',
        'quota_date', 'quota_reset_at', 'daily_limit',
        'daily_specialty_id',
    ];

    protected $casts = [
        'quota_date' => 'date',
        'quota_reset_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExhausted(): bool
    {
        return $this->questions_answered_today >= $this->daily_limit;
    }
}
