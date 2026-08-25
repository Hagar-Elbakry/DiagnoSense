<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\PatientStatus;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'date_of_birth',
        'gender',
        'national_id',
        'status',
        'last_visit_date',
        'next_visit_date',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->date_of_birth ? $this->date_of_birth->age : null,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_patient', 'patient_id', 'doctor_id')
            ->withTimestamps();
    }

    public function medicalHistory(): HasOne
    {
        return $this->hasOne(MedicalHistory::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(related: Visit::class);
    }

    public function latestAiAnalysisResult(): HasOne
    {
        return $this->hasOne(AiAnalysisResult::class)->latest();
    }

    public function aiAnalysisResults(): HasMany
    {
        return $this->hasMany(AiAnalysisResult::class);
    }

    public function latestAiAnalysisValue(string $column): ?string
    {
        return $this->aiAnalysisResults()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->latest('id')
            ->value($column);
    }

    public function labResults(): HasMany
    {
        return $this->hasMany(PatientLabResult::class);
    }

    public static function getStatuses(): array
    {
        return PatientStatus::cases();
    }
}
