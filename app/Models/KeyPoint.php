<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KeyPoint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ai_analysis_result_id',
        'priority',
        'title',
        'insight',
        'is_ai_generated',
        'evidence',
    ];

    protected $casts = [
        'evidence' => 'array',
    ];

    public function aiAnalysisResult(): BelongsTo
    {
        return $this->belongsTo(AiAnalysisResult::class);
    }
}
