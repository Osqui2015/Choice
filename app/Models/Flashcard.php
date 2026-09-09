<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flashcard extends Model
{
    use HasFactory;

    protected $fillable = [
        'code_number', 'specialty_id', 'topic_id', 'section_id',
        'front', 'back', 'justification',
        'source_file', 'catedra', 'subtopic', 'source_date', 'is_active',
    ];

    protected $casts = [
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
}
