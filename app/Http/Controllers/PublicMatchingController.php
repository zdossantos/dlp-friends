<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use App\Support\PublicUrls;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicMatchingController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return redirect(PublicUrls::matchingPath(app()->getLocale()));
    }

    public function show(string $locale): View
    {
        abort_unless(Locale::isSupported($locale), 404);
        app()->setLocale($locale);

        return view('matching.show', [
            'content' => trans('matching'),
            'locale' => $locale,
            'canonical' => PublicUrls::matching($locale),
            'alternates' => [
                'fr' => PublicUrls::matching('fr'),
                'en' => PublicUrls::matching('en'),
                'x-default' => PublicUrls::matching('fr'),
            ],
            'navigationAlternates' => [
                'fr' => PublicUrls::matchingPath('fr'),
                'en' => PublicUrls::matchingPath('en'),
            ],
        ]);
    }
}
