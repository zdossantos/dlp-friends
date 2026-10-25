<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Support\BannedAuthentication;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSessionIsCurrent
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->fresh();
        // Sessions created before this mechanism belong to the initial version.
        if ($user !== null && $request->session()->get('auth_session_version', 0) !== $user->session_version) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort_if($user->status === UserStatus::Banned, 403, BannedAuthentication::message());

            throw new AuthenticationException(guards: ['web']);
        }

        return $next($request);
    }
}
