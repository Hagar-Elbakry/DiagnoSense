<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Doctor;
use App\Models\Medication;
use App\Models\Task;
use App\Models\Patient;

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

    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }

    public function tasks():HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
