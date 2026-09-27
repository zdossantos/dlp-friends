<?php

namespace App\Support;

final class WebPushEndpoint
{
    /** @var list<string> */
    private const HOSTS = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'push.services.mozilla.com',
        'web.push.apple.com',
    ];

    /** @var list<string> */
    private const SUFFIXES = ['.push.apple.com', '.notify.windows.com'];

    public static function isAllowed(string $endpoint): bool
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        return in_array($host, self::HOSTS, true)
            || collect(self::SUFFIXES)->contains(fn (string $suffix): bool => str_ends_with($host, $suffix));
    }
}
