<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SafeHttpsUrl implements ValidationRule
{
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
            if (filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
                $fail(__('partners.announcements.errors.unsafe_url'));
            }

            return;
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            $fail(__('partners.announcements.errors.unsafe_url'));
        }
    }
}
