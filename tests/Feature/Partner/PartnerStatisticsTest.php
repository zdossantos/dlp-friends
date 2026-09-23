<?php

use App\Data\PartnerAnnouncementStatisticsData;
use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerNotificationPreference;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('statistics data returns aggregate rates and no operational counts to partners', function () {
    $announcement = PartnerAnnouncement::factory()->sent()->create();
    PartnerAnnouncementMetric::query()->create([
        'partner_announcement_id' => $announcement->id,
        'prepared_count' => 12,
        'delivered_count' => 10,
        'read_count' => 5,
        'dismissed_count' => 3,
        'unique_click_count' => 2,
        'total_click_count' => 7,
    ]);

    $statistics = PartnerAnnouncementStatisticsData::from(
        $announcement->load('metric'),
    );

    expect($statistics)->toMatchArray([
        'id' => $announcement->id,
        'title' => $announcement->title,
        'status' => PartnerAnnouncementStatus::Sent->value,
        'prepared' => 12,
        'delivered' => 10,
        'read' => 5,
        'dismissed' => 3,
        'unique_clicks' => 2,
        'total_clicks' => 7,
        'read_rate' => 50.0,
        'dismiss_rate' => 30.0,
        'unique_click_rate' => 20.0,
    ])->and(array_keys($statistics))->not->toContain(
        'pending',
        'failed',
        'skipped',
        'deliveries',
        'recipients',
    );
});

test('statistics rates are zero when no notification was delivered', function () {
    $announcement = PartnerAnnouncement::factory()->sent()->create();
    PartnerAnnouncementMetric::query()->create([
        'partner_announcement_id' => $announcement->id,
        'prepared_count' => 4,
        'delivered_count' => 0,
        'read_count' => 0,
        'dismissed_count' => 0,
        'unique_click_count' => 0,
        'total_click_count' => 0,
    ]);

    $statistics = PartnerAnnouncementStatisticsData::from(
        $announcement->load('metric'),
    );

    expect($statistics['read_rate'])->toBe(0.0)
        ->and($statistics['dismiss_rate'])->toBe(0.0)
        ->and($statistics['unique_click_rate'])->toBe(0.0);
});

test('partner statistics are strictly scoped to owned announcements without recipient data', function () {
    $partner = User::factory()->partner()->create();
    $profile = PartnerProfile::factory()->for($partner)->create();
    $announcement = PartnerAnnouncement::factory()->for($profile)->sent()->create([
        'title' => 'Résultats privés du partenaire',
    ]);
    PartnerAnnouncementMetric::query()->create([
        'partner_announcement_id' => $announcement->id,
        'prepared_count' => 10,
        'delivered_count' => 10,
        'read_count' => 5,
        'dismissed_count' => 1,
        'unique_click_count' => 2,
        'total_click_count' => 4,
    ]);
    $recipient = User::factory()->create([
        'email' => 'recipient-secret@example.test',
    ]);
    PartnerAnnouncementDelivery::factory()
        ->for($announcement, 'announcement')
        ->for($recipient)
        ->create(['status' => PartnerDeliveryStatus::Delivered]);

    $otherPartner = User::factory()->partner()->create();
    $otherProfile = PartnerProfile::factory()->for($otherPartner)->create();
    $otherAnnouncement = PartnerAnnouncement::factory()->for($otherProfile)->sent()->create([
        'title' => 'Annonce d’un autre partenaire',
    ]);
    PartnerAnnouncementMetric::query()->create([
        'partner_announcement_id' => $otherAnnouncement->id,
        'prepared_count' => 99,
        'delivered_count' => 99,
    ]);

    $response = $this->actingAs($partner)
        ->get(route('partner.statistics.index'))
        ->assertOk()
        ->assertDontSee('recipient-secret@example.test')
        ->assertDontSee('Annonce d’un autre partenaire');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Partner/Statistics/Index')
        ->has('announcements', 1)
        ->where('announcements.0.id', $announcement->id)
        ->where('announcements.0.read_rate', 50)
        ->where('announcements.0.unique_click_rate', 20)
        ->missing('announcements.0.pending')
        ->missing('announcements.0.failed')
        ->missing('announcements.0.skipped')
        ->missing('announcements.0.deliveries')
        ->missing('announcements.0.recipients'));
});

test('admin statistics include every partner and operational counts without recipient data', function () {
    $admin = User::factory()->admin()->create();
    $partner = User::factory()->partner()->create();
    $profile = PartnerProfile::factory()->for($partner)->published()->create();
    $announcement = PartnerAnnouncement::factory()->for($profile)->create([
        'title' => 'Envoi à reprendre',
        'status' => PartnerAnnouncementStatus::Sending,
    ]);
    PartnerAnnouncementMetric::query()->create([
        'partner_announcement_id' => $announcement->id,
        'prepared_count' => 6,
        'delivered_count' => 2,
        'read_count' => 1,
        'dismissed_count' => 1,
        'unique_click_count' => 1,
        'total_click_count' => 3,
    ]);

    foreach ([
        PartnerDeliveryStatus::Pending,
        PartnerDeliveryStatus::Pending,
        PartnerDeliveryStatus::Failed,
        PartnerDeliveryStatus::Skipped,
        PartnerDeliveryStatus::Delivered,
        PartnerDeliveryStatus::Delivered,
    ] as $index => $status) {
        $recipient = User::factory()->create([
            'email' => "operations-secret-{$index}@example.test",
        ]);
        PartnerAnnouncementDelivery::factory()
            ->for($announcement, 'announcement')
            ->for($recipient)
            ->create(['status' => $status]);
    }

    $secondPartner = User::factory()->partner()->create();
    $secondProfile = PartnerProfile::factory()->for($secondPartner)->create();
    $secondAnnouncement = PartnerAnnouncement::factory()->for($secondProfile)->create([
        'title' => 'Autre partenaire',
    ]);
    $eligibleByDefault = User::factory()->create();
    $optedOut = User::factory()->create();
    PartnerNotificationPreference::query()->create([
        'user_id' => $optedOut->id,
        'enabled' => false,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.partner-statistics.index'))
        ->assertOk()
        ->assertDontSee('operations-secret-0@example.test');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Partners/Statistics')
        ->where('eligibleRecipientCount', 10)
        ->has('announcements', 2)
        ->where('announcements.0.id', $secondAnnouncement->id)
        ->where('announcements.1.id', $announcement->id)
        ->where('announcements.1.pending', 2)
        ->where('announcements.1.failed', 1)
        ->where('announcements.1.skipped', 1)
        ->where('announcements.1.read_rate', 50)
        ->missing('announcements.1.deliveries')
        ->missing('announcements.1.recipients'));
});

test('statistics routes enforce their sibling role boundaries', function () {
    $member = User::factory()->create();
    $partner = User::factory()->partnerOnly()->create();
    $admin = User::factory()->admin()->create();

    $this->get(route('partner.statistics.index'))->assertRedirect(route('login'));
    $this->actingAs($member)->get(route('partner.statistics.index'))->assertForbidden();
    $this->actingAs($partner)->get(route('partner.statistics.index'))->assertOk();
    $this->actingAs($partner)->get(route('admin.partner-statistics.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.partner-statistics.index'))->assertOk();
});
