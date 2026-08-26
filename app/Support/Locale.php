<?php

namespace App\Support;

use Illuminate\Http\Request;

final class Locale
{
    /** @var list<string> */
    public const SUPPORTED = ['fr', 'en'];

    public static function fallback(): string
    {
        return 'fr';
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    public static function fromAcceptLanguage(Request $request): string
    {
        foreach ($request->getLanguages() as $language) {
            $locale = self::normalize($language);

            if (self::isSupported($locale)) {
                return $locale;
            }
        }

        return self::fallback();
    }

    public static function normalize(string $locale): string
    {
        return strtolower(explode('-', str_replace('_', '-', $locale))[0]);
    }
}
