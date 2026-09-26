<?php

use App\Enums\PartnerAnnouncementStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('inertia.testing.ensure_pages_exist', false);
});

test('admins are notified when a partner profile is submitted for moderation', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $member = User::factory()->create();
    $partner = User::factory()->partnerOnly()->create();
    $profile = PartnerProfile::factory()->for($partner)->create();
    PartnerProfileRevision::factory()->for($profile)->create([
        'name_fr' => 'Hôtel partenaire',
        'image_path' => 'partners/profile.jpg',
    ]);

    $this->actingAs($partner)->post(route('partner.profile.submit'))
        ->assertRedirect(route('partner.profile.edit'));

    foreach ([$admin, $otherAdmin] as $recipient) {
        expect($recipient->notifications()->sole()->data)->toMatchArray([
            'category' => 'administration',
            'translation_key' => 'notifications.items.partner_profile_review_requested',
            'parameters' => ['partner' => 'Hôtel partenaire'],
            'target_type' => 'admin_partner_profile_review',
        ]);
    }

    expect($member->notifications()->count())->toBe(0)
        ->and($partner->notifications()->count())->toBe(0);
});

test('admins are notified when a partner announcement is submitted for moderation', function () {
    $admin = User::factory()->admin()->create();
    $partner = User::factory()->partnerOnly()->create();
    $profile = PartnerProfile::factory()->for($partner)->create();
    $announcement = PartnerAnnouncement::factory()->for($profile)->create([
        'title' => 'Offre rentrée',
        'destination_url' => 'https://example.com/offer',
    ]);

    $this->actingAs($partner)
        ->post(route('partner.announcements.submit', $announcement))
        ->assertRedirect(route('partner.announcements.index'));

    expect($announcement->fresh()->status)->toBe(PartnerAnnouncementStatus::PendingApproval)
        ->and($admin->notifications()->sole()->data)->toMatchArray([
            'category' => 'administration',
            'translation_key' => 'notifications.items.partner_announcement_review_requested',
            'parameters' => ['announcement' => 'Offre rentrée'],
            'target_type' => 'admin_partner_announcement_review',
            'target_id' => $announcement->id,
        ]);
});

test('an admin can filter administration notifications and open their moderation target', function () {
    $admin = User::factory()->admin()->create();
    $notification = $admin->notifications()->create([
        'id' => (string) str()->uuid(),
        'type' => 'test',
        'data' => [
            'category' => 'administration',
            'translation_key' => 'notifications.items.partner_announcement_review_requested',
            'parameters' => ['announcement' => 'Offre rentrée'],
            'target_type' => 'admin_partner_announcement_review',
            'target_id' => 123,
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.notifications.index', ['category' => 'administration']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('indexUrl', route('admin.notifications.index', absolute: false))
            ->where('filters.category', 'administration')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.id', $notification->id)
            ->where('notifications.data.0.target_url', route('admin.partner-announcements.index', absolute: false)));
});
