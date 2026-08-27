<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Doctor;

class SupportTicket extends Model
{
    protected $fillable = [
        'doctor_id',
        'name',
        'contact',
        'category',
        'urgency',
        'message',
        'attachment_path',
        'status',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
