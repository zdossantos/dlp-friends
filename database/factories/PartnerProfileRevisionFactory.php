<?php

namespace Database\Factories;

use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartnerProfileRevision> */
class PartnerProfileRevisionFactory extends Factory
{
    protected $model = PartnerProfileRevision::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'partner_profile_id' => PartnerProfile::factory(),
            'name_fr' => fake()->company(),
            'name_en' => fake()->company(),
            'description_fr' => fake()->text(300),
            'description_en' => fake()->text(300),
            'image_path' => 'partners/'.fake()->uuid().'.jpg',
            'status' => PartnerRevisionStatus::Draft,
            'submitted_at' => null,
            'decided_at' => null,
            'decided_by' => null,
            'rejection_reason' => null,
            'draft_key' => 1,
            'expires_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PartnerRevisionStatus::Approved,
            'submitted_at' => now()->subMinute(),
            'decided_at' => now(),
            'draft_key' => null,
        ]);
    }
}
