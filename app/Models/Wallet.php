<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Transaction;

class Wallet extends Model
{
    protected $fillable = [
        'balance',
        'doctor_id',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'source');
    }
}
