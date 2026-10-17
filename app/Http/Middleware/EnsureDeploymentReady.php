<?php

namespace App\Http\Middleware;

use App\Support\DeploymentNotReady;
use App\Support\DeploymentReadiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeploymentReady
{
    public function __construct(private readonly DeploymentReadiness $readiness) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production', 'beta') && ! $request->is('up')) {
            try {
                // Laravel's maintenance middleware controls the explicit operator bypass.
                $this->readiness->assertReady(allowMaintenance: true);
            } catch (DeploymentNotReady) {
                return response(__('deployment.unavailable'), 503, [
                    'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow',
                ]);
            }
        }

        return $next($request);
    }
}
