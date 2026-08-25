<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Doctor;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'next_visit_date',
        'status',
        'doctor_id',
        'patient_id',
    ];

    protected $casts = [
        'next_visit_date' => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
