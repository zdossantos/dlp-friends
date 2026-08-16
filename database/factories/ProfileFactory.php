<?php

namespace Database\Factories;

use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Profile> */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->name(),
            'bio' => fake()->optional()->text(300),
            'visit_frequency' => null,
            'visibility' => ProfileVisibility::Visible,
            'onboarding_completed_at' => null,
        ];
    }

    public function complete(): static
    {
        return $this->state(fn (): array => [
            'visit_frequency' => VisitFrequency::Sometimes,
            'onboarding_completed_at' => now(),
        ]);
    }
}
