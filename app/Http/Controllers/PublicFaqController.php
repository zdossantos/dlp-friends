<?php

namespace App\Http\Controllers;

use App\Support\AuthenticatedHome;
use App\Support\Locale;
use App\Support\PublicUrls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicFaqController extends Controller
{
    public function show(Request $request, string $locale): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->to(AuthenticatedHome::url($request->user()));
        }

        abort_unless(Locale::isSupported($locale), 404);
        app()->setLocale($locale);

        return view('faq.show', [
            'content' => trans('faq'),
            'locale' => $locale,
            'canonical' => PublicUrls::faq($locale),
            'alternates' => [
                'fr' => PublicUrls::faq('fr'),
                'en' => PublicUrls::faq('en'),
                'x-default' => PublicUrls::faq('fr'),
            ],
            'navigationAlternates' => [
                'fr' => PublicUrls::faqPath('fr'),
                'en' => PublicUrls::faqPath('en'),
            ],
        ]);
    }
}
