<?php

namespace App\Contracts;

interface DiscoveryTieBreaker
{
    public function rank(int $profileId): int;
}
