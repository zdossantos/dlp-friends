<?php

namespace App\Http\Controllers;

use App\Support\DeploymentReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

final class DeploymentReadinessController
{
    public function __invoke(DeploymentReadiness $readiness): JsonResponse
    {
        try {
            if (is_file(storage_path('framework/web-draining'))) {
                throw new RuntimeException('Web is draining.');
            }
            $readiness->assertReady();
            Cache::get('deployment:readiness');

            if (config('session.driver') === 'redis') {
                Redis::connection(config('session.connection') ?? 'default')->ping();
            }

            $ready = true;
        } catch (Throwable) {
            // Probes must never disclose credentials, pending migrations or exception traces.
            $ready = false;
        }

        return response()->json(['ready' => $ready], $ready ? 200 : 503, [
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
