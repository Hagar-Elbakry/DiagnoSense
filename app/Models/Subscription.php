<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'doctor_id',
        'plan_id',
        'status',
        'started_at',
        'expires_at',
        'used_summaries',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function usageMetrics(): array
    {
        $limit = $this->plan->summaries_limit;

        return [
            'used' => $this->used_summaries,
            'total' => $limit,
            'remaining' => max(0, $limit - $this->used_summaries),
            'percentage' => $limit > 0 ? round(($this->used_summaries / $limit) * 100, 2) : 0,
        ];
    }
}
