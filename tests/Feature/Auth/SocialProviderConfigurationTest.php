<?php

namespace Tests\Feature\Auth;

use InvalidArgumentException;
use Laravel\Socialite\Contracts\Factory;
use Tests\TestCase;

class SocialProviderConfigurationTest extends TestCase
{
    public function test_only_the_google_socialite_driver_is_registered(): void
    {
        $factory = app(Factory::class);

        $this->assertNotNull($factory->driver('google'));
        $this->expectException(InvalidArgumentException::class);
        $factory->driver('apple');
    }
}
