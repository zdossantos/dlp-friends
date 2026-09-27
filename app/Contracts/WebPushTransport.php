<?php

namespace App\Contracts;

use App\Models\WebPushSubscription;
use App\Support\WebPushResult;

interface WebPushTransport
{
    /** @param array<string, mixed> $payload */
    public function send(WebPushSubscription $subscription, array $payload, string $topic): WebPushResult;
}
