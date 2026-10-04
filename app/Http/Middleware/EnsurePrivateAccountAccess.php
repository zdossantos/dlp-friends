<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePrivateAccountAccess
{
    public function __construct(
        private EnsureCurrentTermsAccepted $terms,
        private EnsureUserCanAccessSocialFeatures $social,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $private = collect($request->route()?->gatherMiddleware() ?? [])
            ->contains(fn ($middleware): bool => is_string($middleware) && in_array(explode(':', $middleware)[0], ['auth', 'social'], true));
        $user = $request->user();
        if (! $private || $user === null || $request->routeIs('logout')) {
            return $next($request);
        }
        if ($user->fresh()?->status === UserStatus::Banned) {
            return $this->social->handle($request, $next);
        }
        if ($request->routeIs('verification.*', 'terms.acceptance.*')) {
            return $next($request);
        }

        return $this->terms->handle($request, $next);
    }
}
