<?php

namespace Database\Factories;

use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartnerProfile> */
class PartnerProfileFactory extends Factory
{
    protected $model = PartnerProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'published_revision_id' => null,
            'is_published' => false,
            'position' => fake()->unique()->numberBetween(0, 100000),
        ];
    }

    public function published(): static
    {
        return $this
            ->state(fn (): array => ['is_published' => true])
            ->afterCreating(function (PartnerProfile $profile): void {
                $revision = PartnerProfileRevision::factory()->for($profile)->approved()->create();

                $profile->update(['published_revision_id' => $revision->id]);
            });
    }
}
