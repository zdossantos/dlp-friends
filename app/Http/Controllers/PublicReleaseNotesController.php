<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use App\Support\PublicReleaseNotes;
use App\Support\PublicUrls;
use Illuminate\View\View;

class PublicReleaseNotesController extends Controller
{
    public function show(string $locale, PublicReleaseNotes $releaseNotes): View
    {
        abort_unless(Locale::isSupported($locale), 404);
        app()->setLocale($locale);

        return view('release-notes.index', [
            'content' => trans('release-notes'),
            'items' => $releaseNotes->items($locale),
            'locale' => $locale,
            'canonical' => PublicUrls::releaseNotes($locale),
            'alternates' => [
                'fr' => PublicUrls::releaseNotes('fr'),
                'en' => PublicUrls::releaseNotes('en'),
                'x-default' => PublicUrls::releaseNotes('fr'),
            ],
            'navigationAlternates' => [
                'fr' => PublicUrls::releaseNotesPath('fr'),
                'en' => PublicUrls::releaseNotesPath('en'),
            ],
        ]);
    }
}
