<?php

namespace App\Http\Controllers;

use App\Support\PublicUrls;
use Illuminate\View\View;

class ExternalBrowserController extends Controller
{
    public function __invoke(?string $locale = null): View
    {
        $locale ??= app()->getLocale();
        app()->setLocale($locale);

        return view('external-browser.show', [
            'locale' => $locale,
            'destination' => PublicUrls::landing($locale),
        ]);
    }
}
