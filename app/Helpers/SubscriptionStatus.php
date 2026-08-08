<?php

namespace App\Helpers;

use App\Models\Subscription;

class SubscriptionStatus
{
    public function __construct(
        public readonly string $mode,
        public readonly ?Subscription $subscription,
        public readonly ?string $status,
    ) {}
}
