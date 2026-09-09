<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'code_number', 'specialty_id', 'topic_id', 'section_id',
        'statement', 'options', 'correct_answer',
        'correct_justification', 'incorrect_justification',
        'source_file', 'source_justification', 'other_sources',
        'catedra', 'subtopic', 'source_date', 'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'source_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }
}
