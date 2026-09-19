<?php

use App\Enums\PartnerRevisionStatus;
use App\Enums\RoleName;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('filesystems.default', 's3');
    config()->set('inertia.testing.ensure_pages_exist', false);
    Storage::fake('s3');
});

test('admin profile moderation is available without the member role and denied to partners', function () {
    $admin = User::factory()->admin()->create();
    $adminRole = Role::query()->where('name', RoleName::Admin)->firstOrFail();
    $admin->roles()->sync([$adminRole->id]);
    $partner = User::factory()->partnerOnly()->create();

    $this->actingAs($admin)
        ->get(route('admin.partner-profiles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Partners/Profiles'));

    $this->actingAs($partner)
        ->get(route('admin.partner-profiles.index'))
        ->assertForbidden();
});

test('the moderation queue exposes pending and published revisions without storage paths', function () {
    $admin = User::factory()->admin()->create();
    $pendingProfile = PartnerProfile::factory()->create();
    $pending = PartnerProfileRevision::factory()->for($pendingProfile)->create([
        'name_fr' => 'Partenaire en attente',
        'status' => PartnerRevisionStatus::PendingApproval,
        'submitted_at' => now(),
        'draft_key' => null,
    ]);
    $publishedProfile = PartnerProfile::factory()->published()->create(['position' => 1]);

    $this->actingAs($admin)
        ->get(route('admin.partner-profiles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingRevisions.0.id', $pending->id)
            ->where('pendingRevisions.0.nameFr', 'Partenaire en attente')
            ->where(
                'pendingRevisions.0.imageUrl',
                route('partner.profile-revisions.image', $pending),
            )
            ->missing('pendingRevisions.0.image_path')
            ->where('publishedProfiles.0.id', $publishedProfile->id)
            ->where(
                'publishedProfiles.0.revision.id',
                $publishedProfile->published_revision_id,
            )
            ->missing('publishedProfiles.0.revision.image_path'));
});

test('approval transactionally publishes a pending revision without mutating the prior approved revision', function () {
    $this->travelTo('2026-09-19 10:00:00');
    $admin = User::factory()->admin()->create();
    $profile = PartnerProfile::factory()->published()->create(['position' => 3]);
    $prior = $profile->publishedRevision;
    $priorAttributes = $prior->getAttributes();
    $pending = PartnerProfileRevision::factory()->for($profile)->create([
        'status' => PartnerRevisionStatus::PendingApproval,
        'submitted_at' => now()->subHour(),
        'draft_key' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.partner-profile-revisions.decide', $pending), [
            'decision' => 'approve',
        ])
        ->assertRedirect(route('admin.partner-profiles.index'));

    expect($pending->fresh()->status)->toBe(PartnerRevisionStatus::Approved)
        ->and($pending->fresh()->decided_by)->toBe($admin->id)
        ->and($pending->fresh()->decided_at?->equalTo(now()))->toBeTrue()
        ->and($pending->fresh()->expires_at?->equalTo(now()->addYears(2)))->toBeTrue()
        ->and($profile->fresh()->published_revision_id)->toBe($pending->id)
        ->and($profile->fresh()->is_published)->toBeTrue()
        ->and($profile->fresh()->position)->toBe(3)
        ->and($prior->fresh()->getAttributes())->toBe($priorAttributes);
});

test('rejection preserves the current publication and records the optional reason', function () {
    $admin = User::factory()->admin()->create();
    $profile = PartnerProfile::factory()->published()->create(['position' => 2]);
    $published = $profile->publishedRevision;
    $pending = PartnerProfileRevision::factory()->for($profile)->create([
        'status' => PartnerRevisionStatus::PendingApproval,
        'submitted_at' => now(),
        'draft_key' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.partner-profile-revisions.decide', $pending), [
            'decision' => 'reject',
            'rejection_reason' => 'Le visuel doit être remplacé.',
        ])
        ->assertRedirect(route('admin.partner-profiles.index'));

    expect($pending->fresh()->status)->toBe(PartnerRevisionStatus::Rejected)
        ->and($pending->fresh()->decided_by)->toBe($admin->id)
        ->and($pending->fresh()->rejection_reason)->toBe('Le visuel doit être remplacé.')
        ->and($profile->fresh()->published_revision_id)->toBe($published->id)
        ->and($profile->fresh()->is_published)->toBeTrue();
});

test('an already decided revision cannot be decided again', function () {
    $admin = User::factory()->admin()->create();
    $revision = PartnerProfileRevision::factory()->approved()->create();

    $this->actingAs($admin)
        ->patch(route('admin.partner-profile-revisions.decide', $revision), [
            'decision' => 'reject',
        ])
        ->assertSessionHasErrors('decision');

    expect($revision->fresh()->status)->toBe(PartnerRevisionStatus::Approved);
});

test('an admin can unpublish a profile without deleting its revisions', function () {
    $admin = User::factory()->admin()->create();
    $profile = PartnerProfile::factory()->published()->create(['position' => 1]);
    $publishedId = $profile->published_revision_id;

    $this->actingAs($admin)
        ->delete(route('admin.partner-profiles.unpublish', $profile))
        ->assertRedirect(route('admin.partner-profiles.index'));

    expect($profile->fresh()->is_published)->toBeFalse()
        ->and($profile->fresh()->published_revision_id)->toBe($publishedId)
        ->and($profile->revisions()->count())->toBe(1);
});

test('ordering accepts exactly the published profile ids and writes consecutive positions', function () {
    $admin = User::factory()->admin()->create();
    [$first, $second, $third] = PartnerProfile::factory()
        ->count(3)
        ->sequence(['position' => 1], ['position' => 2], ['position' => 3])
        ->published()
        ->create();
    $unpublished = PartnerProfile::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.partner-profiles.order'), [
            'ordered_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertRedirect(route('admin.partner-profiles.index'));

    expect($third->fresh()->position)->toBe(1)
        ->and($first->fresh()->position)->toBe(2)
        ->and($second->fresh()->position)->toBe(3);

    $this->actingAs($admin)
        ->patch(route('admin.partner-profiles.order'), [
            'ordered_ids' => [$third->id, $first->id, $unpublished->id],
        ])
        ->assertSessionHasErrors('ordered_ids');

    expect($third->fresh()->position)->toBe(1)
        ->and($first->fresh()->position)->toBe(2)
        ->and($second->fresh()->position)->toBe(3);
});
