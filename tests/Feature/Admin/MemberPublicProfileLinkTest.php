<?php

use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the member catalog links to an available public profile', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $member = User::factory()->withProfile()->create();

    $this->actingAs($admin)
        ->get(route('admin.members.index', ['member' => $member->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members.data.0.public_profile_url', '/admin/members/'.$member->id.'/profile'));

    $this->get(route('admin.members.profile', $member))->assertOk();
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

    $this->get('/admin/members/'.$member->id.'/profile')->assertNotFound();
})->with(['hidden', 'incomplete', 'missing', 'banned', 'unverified', 'inactive_avatar', 'self']);

test('an administrator without member access can preview a profile', function () {
    $admin = User::factory()->admin()->create();
    $admin->roles()->sync([Role::query()->where('name', 'admin')->firstOrFail()->id]);
    $member = User::factory()->withProfile()->create();

    $this->actingAs($admin)
        ->get('/admin/members/'.$member->id.'/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Members/Show')
            ->where('member.id', $member->id)
            ->where('receivedReportsHref', '/admin/conversation-reports?target='.$member->id)
            ->where('backHref', '/admin/members?member='.$member->id));
});

test('ordinary members cannot access the administrative profile preview', function () {
    $member = User::factory()->withProfile()->create();
    $target = User::factory()->withProfile()->create();

    $this->actingAs($member)->get('/admin/members/'.$target->id.'/profile')->assertForbidden();
});

test('member catalog query count stays constant as the page fills', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);
    $this->get(route('admin.members.index'))->assertOk();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->get(route('admin.members.index'))->assertOk();
    $smallPageQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    User::factory()->withProfile()->count(19)->create();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->get(route('admin.members.index'))->assertOk();
    $fullPageQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($fullPageQueries)->toBeLessThanOrEqual($smallPageQueries);
});
