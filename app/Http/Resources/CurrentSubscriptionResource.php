<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Subscription;

class CurrentSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->currentSubscriptionStatus();
        $baseData = [
            'billing_mode' => $status->mode,
            'balance' => (float) ($this->wallet->balance ?? 0),
        ];

        if ($status->mode === 'pay-per-use') {
            return array_merge($baseData, $this->formatPayPerUseData());
        }

        if ($status->mode === 'subscription') {
            return array_merge($baseData, $this->formatSubscriptionData($status->subscription, $status->status));
        }

        return $baseData;
    }

    private function formatPayPerUseData(): array
    {
        return [
            'price_per_file' => (float) Plan::PAY_PER_USE_PRICE,
            'features' => ['All features included'],
        ];
    }

    private function formatSubscriptionData(?Subscription $subscription, string $status): array
    {
        $plan = $subscription->plan;

        return [
            'plan_name' => $plan->name,
            'status' => $status,
            'usage' => $subscription->usageMetrics(),
            'starts_at' => $subscription->started_at->format('D, F j, Y'),
            'expires_at' => $subscription->expires_at->format('D, F j, Y'),
            'features' => $plan->features,
        ];
    }
}
