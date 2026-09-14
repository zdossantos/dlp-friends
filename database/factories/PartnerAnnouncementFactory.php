<?php

namespace Database\Factories;

use App\Enums\PartnerAnnouncementStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartnerAnnouncement> */
class PartnerAnnouncementFactory extends Factory
{
    protected $model = PartnerAnnouncement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'partner_profile_id' => PartnerProfile::factory(),
            'title' => fake()->sentence(5),
            'content' => fake()->text(400),
            'destination_url' => fake()->url(),
            'status' => PartnerAnnouncementStatus::Draft,
            'run_uuid' => null,
            'audience_prepared_at' => null,
            'sending_started_at' => null,
            'sent_at' => null,
            'decided_by' => null,
            'decided_at' => null,
            'rejection_reason' => null,
            'expires_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PartnerAnnouncementStatus::Approved,
            'decided_at' => now(),
        ]);
    }
}
