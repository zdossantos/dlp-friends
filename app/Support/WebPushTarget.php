<?php

namespace App\Support;

use InvalidArgumentException;

final readonly class WebPushTarget
{
    private const ALLOWED_PREFIXES = ['/notifications', '/conversations/', '/events/', '/partner/', '/admin/'];

    public function __construct(public string $url)
    {
        if (! self::isAllowed($url)) {
            throw new InvalidArgumentException('Web Push targets must be allowlisted relative URLs.');
        }
    }

    public static function fallback(): self
    {
        return new self('/notifications');
    }

    public static function isAllowed(string $url): bool
    {
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && ! isset($parts['scheme'], $parts['host'])
            && collect(self::ALLOWED_PREFIXES)->contains(fn (string $prefix): bool => $url === rtrim($prefix, '/') || str_starts_with($url, $prefix));
    }
}
