<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessSocialFeatures
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user?->status === UserStatus::Active
                && $user->age !== null
                && $user->age >= 18,
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
