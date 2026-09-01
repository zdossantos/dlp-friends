<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use App\Support\PublicUrls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PublicLandingController extends Controller
{
    public function redirect(Request $request): SymfonyResponse
    {
        if ($request->user() !== null) {
            return to_route('app');
        }

        $localizedUrl = route('landing.show', ['locale' => app()->getLocale()], absolute: false);

        if ($request->header('X-Inertia')) {
            return Inertia::location($localizedUrl);
        }

        return redirect($localizedUrl);
    }

    public function show(Request $request, string $locale): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return to_route('app');
        }

        if (! Locale::isSupported($locale)) {
            abort(404);
        }

        app()->setLocale($locale);

        $alternates = [
            'fr' => PublicUrls::landing('fr'),
            'en' => PublicUrls::landing('en'),
            'x_default' => PublicUrls::landing(Locale::fallback()),
        ];

        return response()->view('welcome', [
            'seo' => [
                'locale' => $locale,
                'title' => __('common.welcome.seo.title'),
                'description' => __('common.welcome.seo.description'),
                'canonical' => $alternates[$locale],
                'alternates' => $alternates,
                'image' => asset('apple-touch-icon.png'),
            ],
        ])->withCookie(cookie(
            name: 'locale',
            value: $locale,
            minutes: 60 * 24 * 365,
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
