<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Support\BannedAuthentication;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $user = $request->user()?->fresh();
        if ($user?->status === UserStatus::Banned) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, BannedAuthentication::message());
        }

        abort_unless(
            $user?->status === UserStatus::Active
                && $user->age !== null
                && $user->age >= 18,
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
