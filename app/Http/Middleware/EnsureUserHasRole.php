<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless(
            $request->user()?->loadMissing('roles')->hasRole($role),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
