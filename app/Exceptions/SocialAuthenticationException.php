<?php

namespace App\Exceptions;

use RuntimeException;

class SocialAuthenticationException extends RuntimeException
{
    public function __construct(private readonly string $key)
    {
        parent::__construct($key);
    }

    public function translationKey(): string
    {
        return $this->key;
    }
}
