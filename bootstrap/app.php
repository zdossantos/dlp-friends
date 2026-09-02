<?php

use App\Http\Middleware\EnsureProductOnboardingIsComplete;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EnsureUserCanAccessSocialFeatures;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventSearchIndexing;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'profile.complete' => EnsureProfileIsComplete::class,
            'onboarding.complete' => EnsureProductOnboardingIsComplete::class,
            'role' => EnsureUserHasRole::class,
            'social' => EnsureUserCanAccessSocialFeatures::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->append(PreventSearchIndexing::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TransportExceptionInterface $exception, Request $request) {
            if (! $request->routeIs('password.email', 'verification.send')) {
                return null;
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'mail.delivery_failed']);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
