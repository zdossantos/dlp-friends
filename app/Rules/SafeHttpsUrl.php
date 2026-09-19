<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\IpUtils;

final class SafeHttpsUrl implements ValidationRule
{
    /** @var list<string> */
    private const ADDITIONAL_NON_PUBLIC_RANGES = [
        '192.0.0.0/24',
        '192.88.99.0/24',
        '224.0.0.0/4',
        '100::/64',
        '2001:10::/28',
        '2001:20::/28',
        '3fff::/20',
        '5f00::/16',
        'fec0::/10',
        'ff00::/8',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail(__('partners.announcements.errors.unsafe_url'));

            return;
        }

        $parts = parse_url($value);

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || $parts['host'] === ''
            || array_key_exists('user', $parts)
            || array_key_exists('pass', $parts)) {
            $fail(__('partners.announcements.errors.unsafe_url'));

            return;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $ip = trim($host, '[]');

        if ($host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')) {
            $fail(__('partners.announcements.errors.unsafe_url'));

            return;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            if (IpUtils::checkIp($ip, [
                ...IpUtils::PRIVATE_SUBNETS,
                ...self::ADDITIONAL_NON_PUBLIC_RANGES,
            ]) || filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
                $fail(__('partners.announcements.errors.unsafe_url'));
            }

            return;
        }

        if (preg_match('/^(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+)){0,3}$/iD', $host) === 1) {
            $fail(__('partners.announcements.errors.unsafe_url'));

            return;
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            $fail(__('partners.announcements.errors.unsafe_url'));
        }
    }
}
