<?php

namespace App\Http\Controllers;

use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerProfile;
use App\Support\AuthenticatedHome;
use App\Support\Locale;
use App\Support\PublicUrls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicLandingController extends Controller
{
    public function redirect(Request $request): SymfonyResponse
    {
        if ($request->user() !== null) {
            return redirect()->to(AuthenticatedHome::url($request->user()));
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
            return redirect()->to(AuthenticatedHome::url($request->user()));
        }

        if (! Locale::isSupported($locale)) {
            abort(404);
        }

        app()->setLocale($locale);

        $partners = PartnerProfile::query()
            ->published()
            ->whereRelation('publishedRevision', 'status', PartnerRevisionStatus::Approved)
            ->with('publishedRevision')
            ->orderBy('position')
            ->orderBy('id')
            ->limit(6)
            ->get();

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
            'partners' => $partners,
        ])->withCookie(cookie(
            name: 'locale',
            value: $locale,
            minutes: 60 * 24 * 365,
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    public function image(PartnerProfile $partnerProfile): StreamedResponse
    {
        $profile = PartnerProfile::query()
            ->published()
            ->whereRelation('publishedRevision', 'status', PartnerRevisionStatus::Approved)
            ->with('publishedRevision')
            ->findOrFail($partnerProfile->id);
        $path = $profile->publishedRevision?->image_path;

        abort_if($path === null || ! Storage::exists($path), 404);

        return Storage::response($path);
    }
}
