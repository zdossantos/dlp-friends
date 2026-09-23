<?php

use App\Actions\DecidePartnerAnnouncement;
use App\Enums\PartnerAnnouncementStatus;
use App\Enums\RoleName;
use App\Jobs\PreparePartnerAnnouncementAudience;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use App\Models\PartnerSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('inertia.testing.ensure_pages_exist', false);
});

test('announcement moderation is available to admins without the member role and denied to partners', function () {
    $admin = User::factory()->admin()->create();
    $adminRole = Role::query()->where('name', RoleName::Admin)->firstOrFail();
    $admin->roles()->sync([$adminRole->id]);
    $partner = User::factory()->partnerOnly()->create();

    $this->actingAs($admin)
        ->get(route('admin.partner-announcements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Partners/Announcements'));

    $this->actingAs($partner)
        ->get(route('admin.partner-announcements.index'))
        ->assertForbidden();
});

test('the moderation queue exposes pending announcements and the singleton cooldown', function () {
    $admin = User::factory()->admin()->create();
    $pending = PartnerAnnouncement::factory()->create([
        'title' => 'Offre en attente',
        'destination_url' => 'https://offers.example.com/pending',
        'status' => PartnerAnnouncementStatus::PendingApproval,
        'submitted_at' => now(),
    ]);
    PartnerAnnouncement::factory()->approved()->create([
        'title' => 'Déjà traitée',
        'destination_url' => 'https://offers.example.com/approved',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.partner-announcements.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('announcements', 1)
            ->where('announcements.0.id', $pending->id)
            ->where('announcements.0.destinationUrl', 'https://offers.example.com/pending')
            ->where('cooldownDays', 30));

    expect(PartnerSetting::query()->count())->toBe(1);
});

test('approval revalidates the link records the decision and starts delivery without changing content', function () {
    Queue::fake();
    $this->travelTo('2026-09-19 10:00:00');
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->partnerOnly()->create();
    $profile = PartnerProfile::factory()->for($owner)->create();
    $pending = PartnerAnnouncement::factory()->for($profile)->create([
        'title' => 'Titre approuvé',
        'content' => 'Contenu approuvé',
        'destination_url' => 'https://offers.example.com/approved',
        'status' => PartnerAnnouncementStatus::PendingApproval,
        'submitted_at' => now()->subHour(),
    ]);
    $content = $pending->only(['title', 'content', 'destination_url']);

    $this->actingAs($admin)
        ->patch(route('admin.partner-announcements.decide', $pending), [
            'decision' => 'approve',
        ])
        ->assertRedirect(route('admin.partner-statistics.index'));

    expect($pending->fresh()->status)->toBe(PartnerAnnouncementStatus::Sending)
        ->and($pending->fresh()->decided_by)->toBe($admin->id)
        ->and($pending->fresh()->decided_at?->equalTo(now()))->toBeTrue()
        ->and($pending->fresh()->only(['title', 'content', 'destination_url']))->toBe($content)
        ->and($pending->fresh()->sending_started_at?->equalTo(now()))->toBeTrue()
        ->and($pending->fresh()->metric)->not->toBeNull();

    Queue::assertPushed(
        PreparePartnerAnnouncementAudience::class,
        fn (PreparePartnerAnnouncementAudience $job): bool => $job->announcementId === $pending->id,
    );

    expect($owner->notifications()->count())->toBe(1)
        ->and($owner->notifications()->firstOrFail()->data)->toMatchArray([
            'category' => 'partners',
            'translation_key' => 'notifications.items.partner_announcement_approved',
            'parameters' => ['announcement' => 'Titre approuvé'],
            'target_type' => 'partner_announcement_management',
            'target_id' => $pending->id,
        ]);

    $this->actingAs($owner)
        ->get(route('partner.notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.translation_key', 'notifications.items.partner_announcement_approved'));

    $unsafe = PartnerAnnouncement::factory()->create([
        'destination_url' => 'https://localhost/internal',
        'status' => PartnerAnnouncementStatus::PendingApproval,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.partner-announcements.decide', $unsafe), [
            'decision' => 'approve',
        ])
        ->assertSessionHasErrors('destination_url');

    expect($unsafe->fresh()->status)->toBe(PartnerAnnouncementStatus::PendingApproval);
});

test('approval respects the per-partner cooldown including its exact boundary', function () {
    Queue::fake();
    $this->travelTo('2026-09-19 10:00:00');
    $admin = User::factory()->admin()->create();
    PartnerSetting::current()->update(['cooldown_days' => 30]);
    $profile = PartnerProfile::factory()->create();
    PartnerAnnouncement::factory()->for($profile)->create([
        'status' => PartnerAnnouncementStatus::Sent,
        'destination_url' => 'https://example.com/previous',
        'sending_started_at' => now()->subDays(29),
        'sent_at' => now()->subDays(29),
    ]);
    $pending = PartnerAnnouncement::factory()->for($profile)->create([
        'status' => PartnerAnnouncementStatus::PendingApproval,
        'destination_url' => 'https://example.com/pending',
    ]);

    try {
        app(DecidePartnerAnnouncement::class)->approve($admin, $pending);
        $this->fail('A cooldown violation should throw a validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('decision');
    }

    expect($pending->fresh()->status)->toBe(PartnerAnnouncementStatus::PendingApproval);

    $profile->announcements()->where('status', PartnerAnnouncementStatus::Sent)->update([
        'sending_started_at' => now()->subDays(30),
        'sent_at' => now()->subDays(30),
    ]);

    app(DecidePartnerAnnouncement::class)->approve($admin, $pending);

    expect($pending->fresh()->status)->toBe(PartnerAnnouncementStatus::Sending);
});

test('admins can reject or cancel before sending but cannot decide locked states', function () {
    $admin = User::factory()->admin()->create();
    $rejected = PartnerAnnouncement::factory()->create([
        'status' => PartnerAnnouncementStatus::PendingApproval,
        'destination_url' => 'https://example.com/rejected',
    ]);
    $cancelled = PartnerAnnouncement::factory()->approved()->create([
        'destination_url' => 'https://example.com/cancelled',
    ]);
    $sending = PartnerAnnouncement::factory()->create([
        'status' => PartnerAnnouncementStatus::Sending,
        'destination_url' => 'https://example.com/sending',
    ]);

    $this->actingAs($admin)->patch(route('admin.partner-announcements.decide', $rejected), [
        'decision' => 'reject',
        'rejection_reason' => 'Le contenu doit être clarifié.',
    ])->assertRedirect(route('admin.partner-announcements.index'));

    expect($rejected->fresh()->status)->toBe(PartnerAnnouncementStatus::Rejected)
        ->and($rejected->fresh()->rejection_reason)->toBe('Le contenu doit être clarifié.')
        ->and($rejected->fresh()->decided_by)->toBe($admin->id);

    expect($rejected->partnerProfile->user?->notifications()->firstOrFail()->data)
        ->toMatchArray([
            'translation_key' => 'notifications.items.partner_announcement_rejected',
            'parameters' => ['announcement' => $rejected->title],
            'target_type' => 'partner_announcement_management',
        ]);

    $this->actingAs($admin)->patch(route('admin.partner-announcements.decide', $cancelled), [
        'decision' => 'cancel',
    ])->assertRedirect(route('admin.partner-announcements.index'));
    expect($cancelled->fresh()->status)->toBe(PartnerAnnouncementStatus::Cancelled);

    $this->actingAs($admin)->patch(route('admin.partner-announcements.decide', $sending), [
        'decision' => 'cancel',
    ])->assertSessionHasErrors('decision');
    expect($sending->fresh()->status)->toBe(PartnerAnnouncementStatus::Sending);
});

test('an admin can update the singleton cooldown only between one and 365 days', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('admin.partner-settings.update'), ['cooldown_days' => 365])
        ->assertRedirect(route('admin.partner-announcements.index'));

    expect(PartnerSetting::current()->cooldown_days)->toBe(365)
        ->and(PartnerSetting::query()->count())->toBe(1);

    $this->actingAs($admin)
        ->patch(route('admin.partner-settings.update'), ['cooldown_days' => 0])
        ->assertSessionHasErrors('cooldown_days');
    $this->actingAs($admin)
        ->patch(route('admin.partner-settings.update'), ['cooldown_days' => 366])
        ->assertSessionHasErrors('cooldown_days');

    expect(PartnerSetting::current()->cooldown_days)->toBe(365)
        ->and(PartnerSetting::query()->count())->toBe(1);
});
