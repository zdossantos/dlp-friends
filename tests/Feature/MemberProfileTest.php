<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_member_can_open_profile_onboarding(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        InterestSetting::current()->update(['max_selections' => 2]);
        $second = Interest::factory()->create(['name' => 'Spectacles', 'sort_order' => 20]);
        $first = Interest::factory()->create(['name' => 'Attractions', 'sort_order' => 10]);
        Interest::factory()->create(['name' => 'Archivé', 'is_active' => false, 'sort_order' => 0]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('member-profile.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('profile/Create')
                ->has('visitFrequencies', 4)
                ->has('visibilities', 2)
                ->where('interests', [
                    ['id' => $first->id, 'name' => 'Attractions'],
                    ['id' => $second->id, 'name' => 'Spectacles'],
                ])
                ->where('selectedInterestIds', [])
                ->where('interestLimit', 2));
    }

    public function test_member_can_complete_their_profile_with_a_normalized_display_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('member-profile.store'), [
            'display_name' => '  Magic   Friend  ',
            'bio' => 'Toujours partant pour une attraction.',
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
        ]);

        $response->assertRedirect(route('app'));
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'display_name' => 'Magic Friend',
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
        ]);
        $this->assertNotNull($user->fresh()->profile?->onboarding_completed_at);
    }

    public function test_profile_fields_are_validated_at_their_boundaries(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('member-profile.create'))
            ->post(route('member-profile.store'), [
                'display_name' => str_repeat('A', 81),
                'bio' => str_repeat('B', 501),
                'visit_frequency' => 'weekly',
                'visibility' => 'public',
            ])
            ->assertRedirect(route('member-profile.create'))
            ->assertSessionHasErrors([
                'display_name',
                'bio',
                'visit_frequency',
                'visibility',
            ]);

        $this->assertDatabaseMissing('profiles', ['user_id' => $user->id]);
    }

    public function test_repeating_onboarding_updates_instead_of_duplicating_the_profile(): void
    {
        $user = User::factory()->create();
        $payload = [
            'display_name' => 'Magic Friend',
            'bio' => null,
            'visit_frequency' => VisitFrequency::Sometimes->value,
            'visibility' => ProfileVisibility::Visible->value,
        ];

        $this->actingAs($user)->post(route('member-profile.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('member-profile.store'), [
            ...$payload,
            'display_name' => 'Park Pal',
        ])->assertRedirect();

        $this->assertDatabaseCount('profiles', 1);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'display_name' => 'Park Pal',
        ]);
    }

    public function test_incomplete_member_is_redirected_from_profile_display(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('member-profile.show'))
            ->assertRedirect(route('member-profile.create'));
    }

    public function test_member_cannot_update_another_members_profile(): void
    {
        $owner = User::factory()->withProfile()->create();
        $otherMember = User::factory()->withProfile()->create();

        $this->actingAs($otherMember)
            ->patch(route('member-profile.update'), [
                'display_name' => 'Changed',
                'bio' => null,
                'visit_frequency' => VisitFrequency::Rarely->value,
                'visibility' => ProfileVisibility::Hidden->value,
                'user_id' => $owner->id,
            ])
            ->assertRedirect(route('member-profile.show'));

        $this->assertNotSame('Changed', $owner->profile->fresh()->display_name);
        $this->assertSame('Changed', $otherMember->profile->fresh()->display_name);
    }

    public function test_member_can_select_distinct_active_interests_up_to_the_limit(): void
    {
        InterestSetting::current()->update(['max_selections' => 2]);
        [$first, $second] = Interest::factory()->count(2)->create(['is_active' => true]);
        $inactive = Interest::factory()->create(['is_active' => false]);
        $user = User::factory()->create();

        $payload = [
            'display_name' => 'Magic Friend',
            'bio' => null,
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
            'interest_ids' => [$first->id, $second->id],
        ];

        $this->actingAs($user)->post(route('member-profile.store'), $payload)
            ->assertRedirect(route('app'));
        expect($user->fresh()->profile->interests()->pluck('interests.id')->all())
            ->toEqualCanonicalizing([$first->id, $second->id]);

        $this->actingAs($user)->patch(route('member-profile.update'), [
            ...$payload,
            'interest_ids' => [$inactive->id],
        ])->assertSessionHasErrors('interest_ids.0');
    }

    public function test_profile_edit_receives_only_effective_selected_interest_ids(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        [$active, $inactive] = Interest::factory()->count(2)->create();
        $inactive->update(['is_active' => false]);
        $user = User::factory()->withProfile()->create();
        $user->profile->interestHistory()->attach([
            $active->id => ['is_selected' => true],
            $inactive->id => ['is_selected' => true],
        ]);

        $this->actingAs($user)
            ->get(route('member-profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('interests', [
                    ['id' => $active->id, 'name' => $active->name],
                ])
                ->where('selectedInterestIds', [$active->id]));
    }

    public function test_member_cannot_submit_the_same_interest_twice(): void
    {
        $interest = Interest::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('member-profile.store'), [
            'display_name' => 'Magic Friend',
            'bio' => null,
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
            'interest_ids' => [$interest->id, $interest->id],
        ])->assertSessionHasErrors('interest_ids.1');

        $this->assertDatabaseMissing('profiles', ['user_id' => $user->id]);
    }

    public function test_member_cannot_select_more_new_interests_than_the_current_limit(): void
    {
        InterestSetting::current()->update(['max_selections' => 1]);
        $interests = Interest::factory()->count(2)->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('member-profile.store'), [
            'display_name' => 'Magic Friend',
            'bio' => null,
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
            'interest_ids' => $interests->pluck('id')->all(),
        ])->assertSessionHasErrors('interest_ids');

        $this->assertDatabaseMissing('profiles', ['user_id' => $user->id]);
    }

    public function test_grandfathered_profile_can_keep_or_reduce_over_limit_interests_but_cannot_add_one(): void
    {
        [$first, $second, $replacement] = Interest::factory()->count(3)->create();
        $user = User::factory()->withProfile()->create();
        $user->profile->interests()->attach([$first->id, $second->id]);
        InterestSetting::current()->update(['max_selections' => 1]);

        $payload = [
            'display_name' => 'Magic Friend',
            'bio' => 'Bio mise à jour.',
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
            'interest_ids' => [$first->id, $second->id],
        ];

        $this->actingAs($user)->patch(route('member-profile.update'), $payload)
            ->assertRedirect(route('member-profile.show'));
        $this->assertSame('Bio mise à jour.', $user->profile->fresh()->bio);
        expect($user->profile->interests()->pluck('interests.id')->all())
            ->toEqualCanonicalizing([$first->id, $second->id]);

        $this->actingAs($user)->patch(route('member-profile.update'), [
            ...$payload,
            'bio' => 'Modification refusée.',
            'interest_ids' => [$first->id, $replacement->id],
        ])->assertSessionHasErrors('interest_ids');

        $this->assertSame('Bio mise à jour.', $user->profile->fresh()->bio);
        expect($user->profile->interests()->pluck('interests.id')->all())
            ->toEqualCanonicalizing([$first->id, $second->id]);
    }
}
