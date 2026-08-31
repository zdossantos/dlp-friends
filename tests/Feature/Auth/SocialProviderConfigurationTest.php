<?php

namespace Tests\Feature\Auth;

use Laravel\Socialite\Contracts\Factory;
use Tests\TestCase;

class SocialProviderConfigurationTest extends TestCase
{
    public function test_google_and_apple_socialite_drivers_are_registered(): void
    {
        $factory = app(Factory::class);

        $this->assertNotNull($factory->driver('google'));
        $this->assertNotNull($factory->driver('apple'));
    }
}
