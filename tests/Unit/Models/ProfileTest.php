<?php

namespace Tests\Unit\Models;

use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_completion_requires_timestamp_and_active_avatar(): void
    {
        $profile = Profile::factory()->complete()->create();
        $profile->update(['onboarding_completed_at' => null]);

        $this->assertFalse($profile->isComplete());

        $profile->update(['onboarding_completed_at' => now()]);

        $this->assertTrue($profile->isComplete());

        $profile->avatar->update(['is_active' => false]);

        $this->assertFalse($profile->fresh()->isComplete());
    }
}
