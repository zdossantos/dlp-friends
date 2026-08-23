<?php

namespace App\Services;

use App\Contracts\DiscoveryTieBreaker;

final class RandomDiscoveryTieBreaker implements DiscoveryTieBreaker
{
    /** @var array<int, int> */
    private array $ranks = [];

    public function rank(int $profileId): int
    {
        return $this->ranks[$profileId] ??= random_int(PHP_INT_MIN, PHP_INT_MAX);
    }
}
