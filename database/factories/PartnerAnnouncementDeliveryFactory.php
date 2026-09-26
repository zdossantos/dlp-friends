<?php

namespace Database\Factories;

use App\Enums\PartnerDeliveryStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PartnerAnnouncementDelivery> */
class PartnerAnnouncementDeliveryFactory extends Factory
{
    protected $model = PartnerAnnouncementDelivery::class;

    public function configure(): static
    {
        return $this->afterMaking(function (PartnerAnnouncementDelivery $delivery): void {
            $announcement = PartnerAnnouncement::query()
                ->findOrFail((int) $delivery->partner_announcement_id);

            $delivery->forceFill([
                'source_announcement_id' => $announcement->id,
                'announcement_title' => $announcement->title,
                'announcement_content' => $announcement->content,
                'announcement_destination_url' => $announcement->destination_url,
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'partner_announcement_id' => PartnerAnnouncement::factory(),
            'user_id' => User::factory(),
            'notification_id' => null,
            'click_token' => hash('sha256', Str::uuid()->toString()),
            'status' => PartnerDeliveryStatus::Pending,
            'attempts' => 0,
            'last_error' => null,
            'delivered_at' => null,
            'broadcasted_at' => null,
            'read_at' => null,
            'dismissed_at' => null,
            'first_clicked_at' => null,
            'click_count' => 0,
        ];
    }
}
