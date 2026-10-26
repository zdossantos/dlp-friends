<?php

use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the member catalog links to an available public profile', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $member = User::factory()->withProfile()->create();

    $this->actingAs($admin)
        ->get(route('admin.members.index', ['member' => $member->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members.data.0.public_profile_url', '/members/'.$member->id));

    $this->get(route('members.show', $member))->assertOk();
});

test('the member catalog omits links to unavailable public profiles', function (string $state) {
    $admin = User::factory()->withProfile()->admin()->create();
    $member = $state === 'self' ? $admin : User::factory()->withProfile()->create();

    match ($state) {
        'hidden' => $member->profile->update(['visibility' => ProfileVisibility::Hidden]),
        'incomplete' => $member->profile->update(['onboarding_completed_at' => null]),
        'missing' => $member->profile->delete(),
        'banned' => $member->forceFill(['status' => UserStatus::Banned])->save(),
        'unverified' => $member->forceFill(['email_verified_at' => null])->save(),
        'inactive_avatar' => $member->profile->avatar->update(['is_active' => false]),
        'self' => null,
    };

    $this->actingAs($admin)
        ->get(route('admin.members.index', ['member' => $member->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members.data.0.public_profile_url', null));
})->with(['hidden', 'incomplete', 'missing', 'banned', 'unverified', 'inactive_avatar', 'self']);
