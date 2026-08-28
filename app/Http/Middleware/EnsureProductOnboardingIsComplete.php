<?php

namespace App\Http\Middleware;

use App\Enums\ProductOnboardingStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProductOnboardingIsComplete
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $progress = $request->user()?->loadMissing('productOnboarding')->productOnboarding;

        if ($progress?->status !== ProductOnboardingStatus::Completed) {
            return to_route('onboarding.show');
        }

        return $next($request);
    }
}
