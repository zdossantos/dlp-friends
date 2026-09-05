<?php

namespace App\Data;

use App\Enums\SocialProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class PendingSocialIdentity
{
    public const SESSION_KEY = 'social_auth.pending';

    public string $email;

    public function __construct(
        public SocialProvider $provider,
        public string $providerUserId,
        string $email,
    ) {
        $this->email = Str::lower(trim($email));

        if ($this->providerUserId === '' || $this->email === '') {
            throw new InvalidArgumentException('Invalid pending social identity.');
        }
    }

    /** @return array{provider: string, provider_user_id: string, email: string} */
    public function toSession(): array
    {
        return [
            'provider' => $this->provider->value,
            'provider_user_id' => $this->providerUserId,
            'email' => $this->email,
        ];
    }

    /** @param array<array-key, mixed> $payload */
    public static function fromSession(array $payload): self
    {
        if (array_keys($payload) !== ['provider', 'provider_user_id', 'email']
            || ! is_string($payload['provider'])
            || ! is_string($payload['provider_user_id'])
            || ! is_string($payload['email'])) {
            throw new InvalidArgumentException('Invalid pending social identity.');
        }

        $provider = SocialProvider::tryFrom($payload['provider']);

        if ($provider === null) {
            throw new InvalidArgumentException('Invalid pending social identity.');
        }

        return new self($provider, $payload['provider_user_id'], $payload['email']);
    }
}
