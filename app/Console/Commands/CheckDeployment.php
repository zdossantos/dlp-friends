<?php

namespace App\Console\Commands;

use App\Support\DeploymentNotReady;
use App\Support\DeploymentReadiness;
use Illuminate\Console\Command;

class CheckDeployment extends Command
{
    protected $signature = 'deployment:check {--allow-maintenance} {--json}';

    protected $description = 'Check deployment configuration and pending migrations without changing the database';

    public function handle(DeploymentReadiness $readiness): int
    {
        try {
            $readiness->assertReady((bool) $this->option('allow-maintenance'));
            $result = ['ready' => true, 'reason' => 'ready', 'pending' => []];
        } catch (DeploymentNotReady $exception) {
            $result = ['ready' => false, 'reason' => $exception->reason, 'pending' => $exception->pending];
        }

        $this->line($this->option('json') ? json_encode($result, JSON_THROW_ON_ERROR) : $result['reason']);

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
