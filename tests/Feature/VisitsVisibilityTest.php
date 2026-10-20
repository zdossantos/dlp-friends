<?php

use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Enums\VisitsVisibility;
use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function visitIsVisible(User $viewer, Visit $visit): bool
{
    return Visit::query()->visibleTo($viewer)->whereKey($visit->id)->exists();
}

test('sharing with all members requires confirmation of the persisted transition', function () {
    $owner = User::factory()->withProfile()->create();
    expect($owner->fresh()->visits_visibility)->toBe(VisitsVisibility::Hidden);
    $social = $owner->profile->social_links_visibility;
    $this->actingAs($owner)->patch('/visits/visibility', ['visibility' => 'members'])->assertSessionHasErrors('confirmed');
    expect($owner->fresh()->visits_visibility)->toBe(VisitsVisibility::Hidden);
    $this->patch('/visits/visibility', ['visibility' => 'members', 'confirmed' => true])->assertSessionHasNoErrors();
    expect($owner->fresh()->visits_visibility)->toBe(VisitsVisibility::Members);
    $this->patch('/visits/visibility', ['visibility' => 'matches'])->assertSessionHasNoErrors();
    $this->patch('/visits/visibility', ['visibility' => 'members', 'confirmed' => false])->assertSessionHasErrors('confirmed');
    $this->patch('/visits/visibility', ['visibility' => 'hidden'])->assertSessionHasNoErrors();
    // An older form cannot assume that the owner is still sharing publicly.
    $this->patch('/visits/visibility', ['visibility' => 'members'])->assertSessionHasErrors('confirmed');
    expect($owner->fresh()->visits_visibility)->toBe(VisitsVisibility::Hidden);
    expect($owner->profile->fresh()->social_links_visibility)->toBe($social);
});

test('audience and current matches determine access including immediate tightening', function () {
    $viewer = User::factory()->withProfile()->create();
    $owner = User::factory()->withProfile()->create();
    $visit = Visit::factory()->for($owner)->create();
    expect(visitIsVisible($owner, $visit))->toBeTrue();
    expect(visitIsVisible($viewer, $visit))->toBeFalse();
    $owner->forceFill(['visits_visibility' => VisitsVisibility::Matches])->save();
    expect(visitIsVisible($viewer, $visit))->toBeFalse();
    $match = MemberMatch::query()->create(['user_low_id' => min($viewer->id, $owner->id), 'user_high_id' => max($viewer->id, $owner->id)]);
    expect(visitIsVisible($viewer, $visit))->toBeTrue();
    $match->delete();
    expect(visitIsVisible($viewer, $visit))->toBeFalse();
    $owner->forceFill(['visits_visibility' => VisitsVisibility::Members])->save();
    expect(visitIsVisible($viewer, $visit))->toBeTrue();
    $owner->forceFill(['visits_visibility' => VisitsVisibility::Hidden])->save();
    expect(visitIsVisible($viewer, $visit))->toBeFalse();
    expect(Gate::forUser($viewer)->allows('view', $visit))->toBeFalse();
});

test('ineligible or blocked targets never leak through the audience scope', function ($restriction) {
    $viewer = User::factory()->withProfile()->create();
    $owner = User::factory()->withProfile()->create(['visits_visibility' => 'members']);
    $visit = Visit::factory()->for($owner)->create();
    expect(visitIsVisible($viewer, $visit))->toBeTrue();
    match ($restriction) {
        'banned' => $owner->forceFill(['status' => UserStatus::Banned])->save(),
        'deleting' => $owner->forceFill(['status' => UserStatus::PendingDeletion])->save(),
        'unverified' => $owner->forceFill(['email_verified_at' => null])->save(),
        'minor' => $owner->forceFill(['birth_date' => today()->subYears(17)])->save(),
        'role' => $owner->roles()->detach(),
        'hidden' => $owner->profile->update(['visibility' => ProfileVisibility::Hidden]),
        'incomplete' => $owner->profile->update(['onboarding_completed_at' => null]),
        'avatar' => $owner->profile->avatar->update(['is_active' => false]),
        'blocks' => Block::query()->create(['blocker_user_id' => $viewer->id, 'blocked_user_id' => $owner->id]),
        'blocked' => Block::query()->create(['blocker_user_id' => $owner->id, 'blocked_user_id' => $viewer->id]),
    };
    expect(visitIsVisible($viewer, $visit))->toBeFalse();
})->with(['banned', 'deleting', 'unverified', 'minor', 'role', 'hidden', 'incomplete', 'avatar', 'blocks', 'blocked']);

test('hidden profiles retain ownership without granting access to ineligible viewers', function () {
    $owner = User::factory()->withProfile()->create(['visits_visibility' => 'members']);
    $visit = Visit::factory()->for($owner)->create();
    $owner->profile->update(['visibility' => ProfileVisibility::Hidden]);
    expect(visitIsVisible($owner, $visit))->toBeTrue();
    $viewer = User::factory()->withProfile()->create(['status' => UserStatus::Banned]);
    expect(visitIsVisible($viewer, $visit))->toBeFalse();
});
