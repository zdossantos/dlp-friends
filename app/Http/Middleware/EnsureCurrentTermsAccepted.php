<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCurrentTermsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && ! $user->termsAcceptances()->where('terms_version', config('legal.terms.version'))->exists()) {
            return redirect()->route('terms.acceptance.show');
        }

        return $next($request);
    }
}
