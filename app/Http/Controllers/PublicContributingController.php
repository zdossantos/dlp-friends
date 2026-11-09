<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use App\Support\PublicUrls;
use Illuminate\View\View;

class PublicContributingController extends Controller
{
    public function show(string $locale): View
    {
        abort_unless(Locale::isSupported($locale), 404);
        app()->setLocale($locale);

        return view('contributing.show', [
            'content' => trans('contributing'),
            'locale' => $locale,
            'canonical' => PublicUrls::contributing($locale),
            'alternates' => [
                'fr' => PublicUrls::contributing('fr'),
                'en' => PublicUrls::contributing('en'),
                'x-default' => PublicUrls::contributing('fr'),
            ],
            'navigationAlternates' => [
                'fr' => PublicUrls::contributingPath('fr'),
                'en' => PublicUrls::contributingPath('en'),
            ],
        ]);
    }
}
