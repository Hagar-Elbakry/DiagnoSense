<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use App\Exceptions\BillingValidationException;
use App\Models\Subscription;
use App\Notifications\PlanSubscribed;
use App\Notifications\CreditsExhausted;

class SubscriptionService
{
    public function subscribeDoctorToPlan(Doctor $doctor, Plan $plan)
    {
        return DB::transaction(function () use ($doctor, $plan) {
            $doctorWithLock = Doctor::where('id', $doctor->id)->with(['wallet', 'activeSubscription'])->lockForUpdate()->first();
            $this->validateDoctorCanSubscribe($doctor, $plan);
            $this->deductSubscriptionFees($doctorWithLock, $plan);
            $subscription = $this->processSubscriptionRecord($doctorWithLock, $plan);
            $this->recordBillingTransaction($doctorWithLock, $plan);
            DB::afterCommit(function () use ($doctorWithLock, $plan) {
                $this->dispatchSubscriptionNotifications($doctorWithLock, $plan);
            });

            return $subscription;
        });
    }

    private function validateDoctorCanSubscribe(Doctor $doctor, Plan $plan): void
    {
        if ($doctor->activeSubscription && $doctor->activeSubscription->status === 'active') {
            throw new BillingValidationException(__('You already have an active subscription. Please cancel it before subscribing to a new plan.'));
        }

        $balance = $doctor->wallet ? $doctor->wallet->balance : 0;
        if ($balance < $plan->price) {
            $needed = $plan->price - $balance;
            throw new BillingValidationException(__("Insufficient credits. Please recharge EGP{$needed} to your wallet to subscribe to this plan."));
        }
    }

    private function deductSubscriptionFees(Doctor $doctor, Plan $plan): void
    {
        $doctor->wallet->decrement('balance', $plan->price);
        $doctor->update(['billing_mode' => 'subscription']);
    }

    private function processSubscriptionRecord(Doctor $doctor, Plan $plan): Subscription
    {
        return $doctor->subscriptions()->updateOrCreate(
            ['status' => 'active'],
            [
                'plan_id' => $plan->id,
                'started_at' => now(),
                'expires_at' => now()->addDays($plan->duration_days),
                'used_summaries' => 0,
            ]
        );
    }

    private function recordBillingTransaction(Doctor $doctor, Plan $plan): void
    {
        $doctor->transactions()->create([
            'amount' => $plan->price,
            'type' => 'subscription',
            'status' => 'completed',
            'sourceable_type' => get_class($plan),
            'sourceable_id' => $plan->id,
            'description' => "Subscribed to {$plan->name} Plan",
        ]);
    }

    private function dispatchSubscriptionNotifications(Doctor $doctor, Plan $plan): void
    {
        $doctor->notify(new PlanSubscribed($plan->name));

        if ($doctor->wallet->refresh()->balance <= 0) {
            $doctor->notify(new CreditsExhausted);
        }
    }
}