<?php

namespace App\Support;

final class PublicUrls
{
    public static function landing(string $locale): string
    {
        return self::absolute(route('landing.show', ['locale' => $locale], false));
    }

    public static function sitemap(): string
    {
        return self::absolute(route('sitemap', absolute: false));
    }

    public static function terms(string $locale): string
    {
        return self::absolute(route("legal.terms.{$locale}", absolute: false));
    }

    public static function privacy(string $locale): string
    {
        return self::absolute(route("legal.privacy.{$locale}", absolute: false));
    }

    private static function absolute(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
