<?php

namespace App\Services;

use App\Helpers\SubscriptionStatus;
use App\Models\AiAnalysisResult;
use App\Models\Doctor;
use App\Models\Plan;
use App\Notifications\CreditsExhausted;
use App\Notifications\UsageExhausted;
use App\Notifications\UsageThresholdReached;

class AiAnalysisBillingService
{
    public function handleBilling(Doctor $doctor, AiAnalysisResult $analysisRecord): void
    {
        $status = $doctor->currentSubscriptionStatus();
        if ($status->mode === 'subscription' && ($status->status === 'active' || $status->status === 'cancelled' )) {
            $this->handleSubscriptionBilling($status, $doctor);
        } else {
            $this->handlePayPerUseBilling($doctor, $analysisRecord);
        }
    }

    private function handleSubscriptionBilling(SubscriptionStatus $status, Doctor $doctor): void
    {
        $status->subscription->increment('used_summaries');
        $status->subscription->refresh();
        $this->checkAndNotifyUsage($status, $doctor);
    }

    private function handlePayPerUseBilling(Doctor $doctor, AiAnalysisResult $analysisRecord): void
    {
        $doctor->wallet->decrement('balance', Plan::PAY_PER_USE_PRICE);
        $doctor->wallet->refresh();
        if ($doctor->wallet->balance <= 0) {
            $doctor->notify(new CreditsExhausted);
        }
        $doctor->transactions()->create([
            'amount' => Plan::PAY_PER_USE_PRICE,
            'type' => 'usage',
            'status' => 'completed',
            'description' => 'Pay-per-use Analysis File',
            'sourceable_type' => AiAnalysisResult::class,
            'sourceable_id' => $analysisRecord->id,
        ]);
    }

    private function checkAndNotifyUsage(SubscriptionStatus $status, Doctor $doctor): void
    {
        $subscription = $status->subscription;
        $usage = $subscription->usageMetrics();
        if ($usage['percentage'] >= 80 && ! $doctor->notifications()->where('type', UsageThresholdReached::class)->exists()) {
            $doctor->notify(new UsageThresholdReached(80));
        }
        if ($usage['used'] >= $usage['total']) {
            $doctor->notify(new UsageExhausted);
        }
    }
}
