<?php

namespace Tests\Feature;

use App\Enums\PartnerDeliveryStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_notifications_can_be_filtered_and_expose_only_opaque_engagement_routes(): void
    {
        $member = User::factory()->withProfile()->create();
        [$notification, $delivery] = $this->partnerNotification($member);
        $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'category' => 'events',
                'translation_key' => 'notifications.items.event_changed',
                'parameters' => ['event' => 'Sortie'],
                'target_type' => 'event',
                'target_id' => 999,
            ],
        ]);

        $this->actingAs($member)
            ->get(route('notifications.index', ['category' => 'partners']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->where('filters.category', 'partners')
                ->has('notifications.data', 1)
                ->where('notifications.data.0.id', $notification->id)
                ->where('notifications.data.0.category', 'partners')
                ->where('notifications.data.0.translation_key', 'notifications.items.partner_announcement')
                ->where('notifications.data.0.target_url', route('partner-announcements.click', $delivery->click_token, absolute: false))
                ->where('notifications.data.0.dismiss_url', route('notifications.partner-announcements.dismiss', $notification, absolute: false))
                ->where('notifications.data.0.action_label', __('notifications.actions.open_partner_announcement'))
                ->where('notifications.data.0.content', $delivery->announcement_content)
                ->missing('notifications.data.0.user_id')
                ->missing('notifications.data.0.email'));
    }

    public function test_regular_notifications_do_not_expose_partner_dismissal_controls(): void
    {
        $member = User::factory()->withProfile()->create();
        $notification = $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'category' => 'events',
                'translation_key' => 'notifications.items.event_changed',
                'parameters' => ['event' => 'Sortie'],
                'target_type' => 'event',
                'target_id' => 999,
            ],
        ]);

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.data.0.id', $notification->id)
                ->missing('notifications.data.0.dismiss_url')
                ->missing('notifications.data.0.action_label'));
    }

    /** @return array{DatabaseNotification, PartnerAnnouncementDelivery} */
    private function partnerNotification(User $member): array
    {
        $announcement = PartnerAnnouncement::factory()->create([
            'destination_url' => 'https://offers.example.com/frozen-destination',
        ]);
        PartnerAnnouncementMetric::query()->create(['partner_announcement_id' => $announcement->id]);
        $notification = $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'partner-announcement',
            'data' => [
                'category' => 'partners',
                'translation_key' => 'notifications.items.partner_announcement',
                'parameters' => ['announcement' => $announcement->title],
                'target_type' => 'partner_announcement',
                'target_id' => $announcement->id,
            ],
        ]);
        $delivery = PartnerAnnouncementDelivery::factory()
            ->for($announcement, 'announcement')
            ->for($member)
            ->create([
                'notification_id' => $notification->id,
                'status' => PartnerDeliveryStatus::Delivered,
                'delivered_at' => now(),
            ]);

        return [$notification, $delivery];
    }
}
