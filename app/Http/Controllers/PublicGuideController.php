<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use App\Support\PublicUrls;
use Illuminate\View\View;

class PublicGuideController extends Controller
{
    public function show(string $locale, string $guide): View
    {
        abort_unless(Locale::isSupported($locale) && in_array($guide, ['friendships', 'solo_visit'], true), 404);
        app()->setLocale($locale);

        $urls = $guide === 'friendships'
            ? [
                'absolute' => fn (string $language): string => PublicUrls::friendships($language),
                'path' => fn (string $language): string => PublicUrls::friendshipsPath($language),
            ]
            : [
                'absolute' => fn (string $language): string => PublicUrls::soloVisit($language),
                'path' => fn (string $language): string => PublicUrls::soloVisitPath($language),
            ];

        return view('guides.show', [
            'content' => trans("guides.{$guide}"),
            'guide' => $guide,
            'locale' => $locale,
            'canonical' => $urls['absolute']($locale),
            'alternates' => [
                'fr' => $urls['absolute']('fr'),
                'en' => $urls['absolute']('en'),
                'x-default' => $urls['absolute']('fr'),
            ],
            'navigationAlternates' => [
                'fr' => $urls['path']('fr'),
                'en' => $urls['path']('en'),
            ],
        ]);
    }
}
