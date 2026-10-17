<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureBetaAccess
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('beta') || $request->routeIs('robots', 'logout')) {
            return $next($request);
        }

        abort_if($request->routeIs('register', 'register.store', 'social.registration.*'), Response::HTTP_FORBIDDEN);

        $user = $request->user();

        if ($user === null) {
            if ($request->routeIs(
                'login', 'login.store',
                'password.request', 'password.email', 'password.reset', 'password.update',
                'two-factor.login', 'two-factor.login.store',
                'passkey.login', 'passkey.login-options',
                'social.redirect', 'social.callback.*', 'locale.update',
            )) {
                return $next($request);
            }

            throw new AuthenticationException(guards: ['web']);
        }

        // Re-read roles and status so an existing session cannot retain revoked access.
        $currentUser = $user->fresh(['roles']);
        abort_unless($currentUser !== null, Response::HTTP_FORBIDDEN);
        Gate::forUser($currentUser)->authorize('accessBeta', User::class);

        return $next($request);
    }
}
