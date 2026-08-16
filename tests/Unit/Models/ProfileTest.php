<?php

namespace Tests\Unit\Models;

use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_completion_requires_the_explicit_timestamp(): void
    {
        $profile = Profile::factory()->make([
            'onboarding_completed_at' => null,
        ]);

        $this->assertFalse($profile->isComplete());

        $profile->onboarding_completed_at = now();

        $this->assertTrue($profile->isComplete());
    }
}
