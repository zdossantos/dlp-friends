<?php

namespace App\Rules;

use App\Enums\SocialNetwork;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class SocialNetworkUrl implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $networkValue = data_get($this->data, str_replace('.url', '.network', $attribute));
        $network = is_string($networkValue) ? SocialNetwork::tryFrom($networkValue) : null;
        $parts = is_string($value) ? parse_url($value) : false;

        if (! is_string($value)
            || filter_var($value, FILTER_VALIDATE_URL) === false
            || str_contains($value, '\\')
            || ! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https') {
            $fail(__('profile.social_links.invalid_url'));

            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            $fail(__('profile.social_links.unsafe_url'));

            return;
        }

        // The network field has its own validation error when missing or unsupported.
        if ($network === null) {
            return;
        }

        if (! in_array(strtolower($parts['host'] ?? ''), $network->hosts(), true)) {
            $fail(__('profile.social_links.wrong_network', [
                'network' => __('profile.social_links.networks.'.$network->value),
                'domain' => $network->hosts()[0],
            ]));
        }
    }
}
