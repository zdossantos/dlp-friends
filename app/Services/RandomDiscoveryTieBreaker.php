<?php

namespace App\Services;

use App\Contracts\DiscoveryTieBreaker;

final readonly class RandomDiscoveryTieBreaker implements DiscoveryTieBreaker
{
    private string $seed;

    public function __construct()
    {
        $this->seed = random_bytes(32);
    }

    public function rank(int $profileId): int
    {
        // Stable within this instance without retaining every streamed candidate.
        return (int) hexdec(substr(hash('sha256', $this->seed.':'.$profileId), 0, 15));
    }
}
