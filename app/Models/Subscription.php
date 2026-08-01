<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Doctor;
use App\Models\Plan;

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
}
