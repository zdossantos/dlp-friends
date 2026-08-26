<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $userLocale = $request->user() instanceof User ? $request->user()->locale : null;
        $cookieLocale = $request->cookie('locale');

        $locale = match (true) {
            is_string($userLocale) && Locale::isSupported($userLocale) => $userLocale,
            is_string($cookieLocale) && Locale::isSupported($cookieLocale) => $cookieLocale,
            default => Locale::fromAcceptLanguage($request),
        };

        app()->setLocale($locale);

        return $next($request);
    }
}
