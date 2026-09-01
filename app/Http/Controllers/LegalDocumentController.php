<?php

namespace App\Http\Controllers;

use App\Support\PublicUrls;
use Illuminate\Contracts\View\View;
use RuntimeException;

class LegalDocumentController extends Controller
{
    public function terms(string $locale): View
    {
        return $this->show('terms', $locale);
    }

    public function privacy(string $locale): View
    {
        return $this->show('privacy', $locale);
    }

    private function show(string $document, string $locale): View
    {
        app()->setLocale($locale);
        if (app()->environment('production') && blank(config('legal.contact_email'))) {
            throw new RuntimeException('LEGAL_CONTACT_EMAIL must be configured before publishing legal pages.');
        }

        $url = $document === 'terms' ? PublicUrls::terms(...) : PublicUrls::privacy(...);

        return view('legal.show', [
            'document' => trans("legal.{$document}"), 'locale' => $locale,
            'contactEmail' => config('legal.contact_email'), 'canonical' => $url($locale),
            'alternates' => ['fr' => $url('fr'), 'en' => $url('en'), 'x-default' => $url('fr')],
        ]);
    }
}
