<?php

namespace Tests\Unit;

use App\Actions\SetInterestStatus;
use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetInterestStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_archiving_suspends_selections_and_immediately_frees_member_capacity(): void
    {
        InterestSetting::current()->update(['max_selections' => 1]);
        $archived = Interest::factory()->create(['is_active' => true]);
        $replacement = Interest::factory()->create(['is_active' => true]);
        $member = User::factory()->withProfile()->create();
        $member->profile->interestHistory()->attach($archived, ['is_selected' => true]);

        app(SetInterestStatus::class)->handle($archived, false);

        expect($archived->fresh()->is_active)->toBeFalse()
            ->and($member->profile->interests()->count())->toBe(0);
        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $member->profile->id,
            'interest_id' => $archived->id,
            'is_selected' => false,
        ]);

        $this->actingAs($member)->patch(route('member-profile.update'), [
            'display_name' => $member->profile->display_name,
            'bio' => $member->profile->bio,
            'visit_frequency' => VisitFrequency::Sometimes->value,
            'visibility' => ProfileVisibility::Visible->value,
            'interest_ids' => [$replacement->id],
        ])->assertRedirect(route('member-profile.show'));

        expect($member->profile->interests()->pluck('interests.id')->all())
            ->toBe([$replacement->id]);
    }

    public function test_reactivation_restores_only_profiles_with_capacity(): void
    {
        InterestSetting::current()->update(['max_selections' => 1]);
        $archived = Interest::factory()->create(['is_active' => true]);
        $replacement = Interest::factory()->create(['is_active' => true]);
        $availableProfile = User::factory()->withProfile()->create()->profile;
        $fullProfile = User::factory()->withProfile()->create()->profile;
        $availableProfile->interestHistory()->attach($archived, ['is_selected' => true]);
        $fullProfile->interestHistory()->attach($archived, ['is_selected' => true]);

        $action = app(SetInterestStatus::class);
        $action->handle($archived, false);
        $fullProfile->interestHistory()->attach($replacement, ['is_selected' => true]);
        $action->handle($archived, true);

        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $availableProfile->id,
            'interest_id' => $archived->id,
            'is_selected' => true,
        ]);
        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $fullProfile->id,
            'interest_id' => $archived->id,
            'is_selected' => false,
        ]);
    }

    public function test_reactivation_rechecks_capacity_for_a_legacy_inactive_selection(): void
    {
        InterestSetting::current()->update(['max_selections' => 1]);
        $legacyInactive = Interest::factory()->create(['is_active' => false]);
        $replacement = Interest::factory()->create(['is_active' => true]);
        $profile = User::factory()->withProfile()->create()->profile;
        $profile->interestHistory()->attach([
            $legacyInactive->id => ['is_selected' => true],
            $replacement->id => ['is_selected' => true],
        ]);

        app(SetInterestStatus::class)->handle($legacyInactive, true);

        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $profile->id,
            'interest_id' => $legacyInactive->id,
            'is_selected' => false,
        ]);
        expect($profile->interests()->pluck('interests.id')->all())
            ->toBe([$replacement->id]);
    }

    public function test_repeated_stale_reactivation_does_not_restore_a_suspended_selection(): void
    {
        InterestSetting::current()->update(['max_selections' => 1]);
        $archived = Interest::factory()->create(['is_active' => true]);
        $replacement = Interest::factory()->create(['is_active' => true]);
        $profile = User::factory()->withProfile()->create()->profile;
        $profile->interestHistory()->attach($archived, ['is_selected' => true]);

        $action = app(SetInterestStatus::class);
        $action->handle($archived, false);
        $profile->interestHistory()->attach($replacement, ['is_selected' => true]);
        $action->handle($archived, true);

        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $profile->id,
            'interest_id' => $archived->id,
            'is_selected' => false,
        ]);

        $profile->interests()->detach($replacement);
        $action->handle($archived->fresh(), true);

        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $profile->id,
            'interest_id' => $archived->id,
            'is_selected' => false,
        ]);
        expect($profile->interests()->count())->toBe(0);
    }
}
