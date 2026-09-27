<?php

namespace App\Support;

final readonly class WebPushResult
{
    public function __construct(
        public bool $successful,
        public bool $subscriptionExpired = false,
        public ?int $statusCode = null,
    ) {}
}
