<?php

namespace App\Models;

use App\Helpers\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Doctor extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'user_id',
        'billing_mode',
        'specialization',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class, 'doctor_patient', 'doctor_id', 'patient_id')
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'cancelled'])
            ->where('expires_at', '>', now())
            ->whereHas('plan', function ($query) {
                $query->whereColumn('subscriptions.used_summaries', '<', 'plans.summaries_limit');
            })
            ->latest();
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function currentSubscriptionStatus(): SubscriptionStatus
    {
        if ($this->billing_mode === 'pay-per-use') {
            return new SubscriptionStatus('pay-per-use', null, null);
        } elseif ($this->activeSubscription) {
            return new SubscriptionStatus('subscription', $this->activeSubscription->load('plan'), $this->activeSubscription->status);
        } elseif ($this->latestSubscription) {
            return new SubscriptionStatus('subscription', $this->latestSubscription->load('plan'), 'expired');
        } else {
            return new SubscriptionStatus('none', null, null);
        }
    }
}
