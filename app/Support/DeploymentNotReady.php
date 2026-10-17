<?php

namespace App\Support;

use RuntimeException;

final class DeploymentNotReady extends RuntimeException
{
    /** @param list<string> $pending */
    public function __construct(public readonly string $reason, public readonly array $pending = [])
    {
        parent::__construct('Deployment is not ready.');
    }
}
