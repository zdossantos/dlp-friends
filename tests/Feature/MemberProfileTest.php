<?php

namespace Tests\Feature;

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
use App\Models\Avatar;
use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $user = User::factory()->create(['birth_date' => today()->subYears(31)]);

        $this->actingAs($user)
            ->get(route('member-profile.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('profile/Create')
                ->where('age', 31)
                ->has('visitFrequencies', 4)
                ->has('visibilities', 2)
                ->where('interests', [
                    ['id' => $first->id, 'name' => 'Attractions'],
                    ['id' => $second->id, 'name' => 'Spectacles'],
                ])
                ->where('selectedInterestIds', [])
                ->where('interestLimit', 2));
    }

    public function test_profile_form_only_offers_active_avatars_in_catalog_order(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $later = $this->avatar(['name' => 'Brume', 'sort_order' => 20]);
        $earlier = $this->avatar(['name' => 'Aurore', 'sort_order' => 10]);
        $this->avatar(['name' => 'Archivé', 'sort_order' => 0, 'is_active' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('member-profile.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('avatars', [
                [
                    'id' => $earlier->id,
                    'name' => 'Aurore',
                    'image_url' => route('avatars.image', $earlier),
                    'primary_color' => '#7C3AED',
                    'secondary_color' => '#EC4899',
                ],
                [
                    'id' => $later->id,
                    'name' => 'Brume',
                    'image_url' => route('avatars.image', $later),
                    'primary_color' => '#7C3AED',
                    'secondary_color' => '#EC4899',
                ],
            ]));
    }

    public function test_incomplete_admin_can_reach_avatar_management_to_bootstrap_the_catalog(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('member-profile.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageAvatars', true));
    }

    public function test_member_must_select_an_active_avatar_to_complete_their_profile(): void
    {
        $inactive = $this->avatar(['is_active' => false]);
        $user = User::factory()->create();
        $payload = [
            'display_name' => 'Magic Friend',
            'bio' => null,
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
        ];

        $this->actingAs($user)->post(route('member-profile.store'), $payload)
            ->assertSessionHasErrors('avatar_id');
        $this->actingAs($user)->post(route('member-profile.store'), [
            ...$payload,
            'avatar_id' => $inactive->id,
        ])->assertSessionHasErrors('avatar_id');

        expect($user->fresh()->profile?->isComplete() ?? false)->toBeFalse();
    }

    public function test_member_can_complete_their_profile_with_an_active_avatar(): void
    {
        $avatar = $this->avatar();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('member-profile.store'), [
            'avatar_id' => $avatar->id,
            'display_name' => 'Magic Friend',
            'bio' => null,
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
        ])->assertRedirect(route('onboarding.show'));

        expect($user->fresh()->profile->avatar->is($avatar))->toBeTrue()
            ->and($user->fresh()->profile->isComplete())->toBeTrue();
    }

    public function test_write_time_avatar_revalidation_rolls_back_new_profile_creation(): void
    {
        $avatar = $this->avatar();
        $user = User::factory()->create();
        $catalogChanged = false;

        DB::listen(function (QueryExecuted $query) use (&$catalogChanged, $avatar): void {
            if (
                $catalogChanged
                || ! str_contains($query->sql, 'count(*)')
                || ! str_contains($query->sql, 'aggregate')
                || ! str_contains($query->sql, 'avatars')
                || ! str_contains($query->sql, 'is_active')
            ) {
                return;
            }

            $catalogChanged = true;
            Avatar::query()->whereKey($avatar->id)->update(['is_active' => false]);
        });

        $this->actingAs($user)->post(route('member-profile.store'), [
            'avatar_id' => $avatar->id,
            'display_name' => 'Création refusée',
            'bio' => null,
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
        ])->assertSessionHasErrors('avatar_id');

        expect($catalogChanged)->toBeTrue();
        $this->assertDatabaseMissing('profiles', ['user_id' => $user->id]);
    }

    public function test_profile_becomes_incomplete_when_its_selected_avatar_is_archived(): void
    {
        $avatar = $this->avatar();
        $user = User::factory()->withProfile()->create();
        $user->profile->update(['avatar_id' => $avatar->id]);

        expect($user->profile->fresh()->isComplete())->toBeTrue();

        $avatar->update(['is_active' => false]);

        expect($user->profile->fresh()->isComplete())->toBeFalse();
        $this->actingAs($user)
            ->get(route('member-profile.show'))
            ->assertRedirect(route('member-profile.create'));
    }

    public function test_member_can_complete_their_profile_with_a_normalized_display_name(): void
    {
        $avatar = $this->avatar();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('member-profile.store'), [
            'avatar_id' => $avatar->id,
            'display_name' => '  Magic   Friend  ',
            'bio' => 'Toujours partant pour une attraction.',
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
        ]);

        $response->assertRedirect(route('onboarding.show'));
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
        $avatar = $this->avatar();
        $user = User::factory()->create();
        $payload = [
            'avatar_id' => $avatar->id,
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
        $avatar = $this->avatar();
        $user = User::factory()->create();

        $payload = [
            'avatar_id' => $avatar->id,
            'display_name' => 'Magic Friend',
            'bio' => null,
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
            'interest_ids' => [$first->id, $second->id],
        ];

        $this->actingAs($user)->post(route('member-profile.store'), $payload)
            ->assertRedirect(route('onboarding.show'));
        expect($user->fresh()->profile->interests()->pluck('interests.id')->all())
            ->toEqualCanonicalizing([$first->id, $second->id]);
        $user->productOnboarding()->updateOrCreate([], [
            'status' => ProductOnboardingStatus::Completed,
            'step' => null,
        ]);

        $this->actingAs($user->fresh())->patch(route('member-profile.update'), [
            ...$payload,
            'interest_ids' => [$inactive->id],
        ])->assertSessionHasErrors('interest_ids.0');
    }

    public function test_url_encoded_numeric_interest_ids_are_normalized_before_synchronization(): void
    {
        $interest = Interest::factory()->create();
        $avatar = $this->avatar();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->post(route('member-profile.store'), [
                'avatar_id' => $avatar->id,
                'display_name' => 'Magic Friend',
                'bio' => null,
                'visit_frequency' => VisitFrequency::Often->value,
                'visibility' => ProfileVisibility::Visible->value,
                'interest_ids' => [(string) $interest->id],
            ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('onboarding.show'));

        expect($user->fresh()->profile->interests()->pluck('interests.id')->all())
            ->toBe([$interest->id]);
    }

    public function test_existing_profile_is_locked_by_interest_sync_before_fields_are_updated(): void
    {
        $interest = Interest::factory()->create();
        $user = User::factory()->withProfile()->create();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($user)->patch(route('member-profile.update'), [
            'display_name' => 'Magic Friend',
            'bio' => 'Nouvelle bio.',
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
            'interest_ids' => [$interest->id],
        ])->assertRedirect(route('member-profile.show'));

        $this->assertProfileLockPrecedesUpdate($queries);
    }

    public function test_repeated_onboarding_locks_existing_profile_before_fields_are_updated(): void
    {
        $interest = Interest::factory()->create();
        $user = User::factory()->withProfile()->create();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($user)->post(route('member-profile.store'), [
            'display_name' => 'Magic Friend',
            'bio' => 'Nouvelle bio.',
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
            'interest_ids' => [$interest->id],
        ])->assertRedirect(route('app'));

        $this->assertProfileLockPrecedesUpdate($queries);
    }

    /** @param list<string> $queries */
    private function assertProfileLockPrecedesUpdate(array $queries): void
    {
        $profileLockIndex = collect($queries)->search(
            fn (string $sql): bool => preg_match(
                '/select \* from [`"]profiles[`"] where [`"]profiles[`"]\.[`"]id[`"] = \?/i',
                $sql,
            ) === 1,
        );
        $profileUpdateIndex = collect($queries)->search(
            fn (string $sql): bool => preg_match('/update [`"]profiles[`"] set /i', $sql) === 1,
        );

        expect($profileLockIndex)->not->toBeFalse()
            ->and($profileUpdateIndex)->not->toBeFalse()
            ->and($profileLockIndex)->toBeLessThan($profileUpdateIndex);
    }

    /** @param array<string, mixed> $attributes */
    private function avatar(array $attributes = []): Avatar
    {
        return Avatar::query()->create([
            'name' => 'Aurore',
            'image_path' => 'avatars/'.fake()->uuid().'.png',
            'primary_color' => '#7C3AED',
            'secondary_color' => '#EC4899',
            'is_active' => true,
            'sort_order' => 0,
            ...$attributes,
        ]);
    }

    public function test_write_time_interest_revalidation_rolls_back_profile_fields_and_pivots(): void
    {
        [$keep, $becomesInactive] = Interest::factory()->count(2)->create();
        $user = User::factory()->withProfile()->create();
        $user->profile->interests()->attach($keep);
        $originalDisplayName = $user->profile->display_name;
        $originalBio = $user->profile->bio;
        $catalogChanged = false;
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$catalogChanged, &$queries, $becomesInactive): void {
            $queries[] = $query->sql;

            if (
                $catalogChanged
                || ! str_contains($query->sql, 'count(*)')
                || ! str_contains($query->sql, 'aggregate')
                || ! str_contains($query->sql, 'interests')
                || ! str_contains($query->sql, 'is_active')
            ) {
                return;
            }

            $catalogChanged = true;
            Interest::query()
                ->whereKey($becomesInactive->id)
                ->update(['is_active' => false]);
        });

        $response = $this->actingAs($user)->patch(route('member-profile.update'), [
            'display_name' => 'Modification refusée',
            'bio' => 'Cette bio doit être annulée.',
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
            'interest_ids' => [$becomesInactive->id],
        ]);

        expect($catalogChanged)->toBeTrue(implode(PHP_EOL, $queries));
        $response->assertSessionHasErrors('interest_ids');
        $this->assertDatabaseHas('profiles', [
            'id' => $user->profile->id,
            'display_name' => $originalDisplayName,
            'bio' => $originalBio,
        ]);
        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $user->profile->id,
            'interest_id' => $keep->id,
            'is_selected' => true,
        ]);
        $this->assertDatabaseMissing('interest_profile', [
            'profile_id' => $user->profile->id,
            'interest_id' => $becomesInactive->id,
        ]);
    }

    public function test_write_time_interest_revalidation_rolls_back_new_profile_creation(): void
    {
        $becomesInactive = Interest::factory()->create();
        $avatar = $this->avatar();
        $user = User::factory()->create();
        $catalogChanged = false;

        DB::listen(function (QueryExecuted $query) use (&$catalogChanged, $becomesInactive): void {
            if (
                $catalogChanged
                || ! str_contains($query->sql, 'count(*)')
                || ! str_contains($query->sql, 'aggregate')
                || ! str_contains($query->sql, 'interests')
                || ! str_contains($query->sql, 'is_active')
            ) {
                return;
            }

            $catalogChanged = true;
            Interest::query()
                ->whereKey($becomesInactive->id)
                ->update(['is_active' => false]);
        });

        $this->actingAs($user)->post(route('member-profile.store'), [
            'avatar_id' => $avatar->id,
            'display_name' => 'Modification refusée',
            'bio' => 'Cette création doit être annulée.',
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
            'interest_ids' => [$becomesInactive->id],
        ])->assertSessionHasErrors('interest_ids');

        expect($catalogChanged)->toBeTrue();
        $this->assertDatabaseMissing('profiles', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('interest_profile', [
            'interest_id' => $becomesInactive->id,
        ]);
    }

    public function test_profile_edit_receives_only_effective_selected_interest_ids(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $active = Interest::factory()->create(['sort_order' => 10]);
        $inactive = Interest::factory()->create(['is_active' => false, 'sort_order' => 0]);
        $suspended = Interest::factory()->create(['sort_order' => 20]);
        $user = User::factory()->withProfile()->create([
            'birth_date' => today()->subYears(31),
        ]);
        $user->profile->interestHistory()->attach([
            $active->id => ['is_selected' => true],
            $inactive->id => ['is_selected' => true],
            $suspended->id => ['is_selected' => false],
        ]);

        $this->actingAs($user)
            ->get(route('member-profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('interests', [
                    ['id' => $active->id, 'name' => $active->name],
                    ['id' => $suspended->id, 'name' => $suspended->name],
                ])
                ->where('age', 31)
                ->where('selectedInterestIds', [$active->id]));
    }

    public function test_profile_save_preserves_an_omitted_inactive_legacy_association(): void
    {
        $legacyInactive = Interest::factory()->create(['is_active' => false]);
        $user = User::factory()->withProfile()->create();
        $user->profile->interestHistory()->attach($legacyInactive, ['is_selected' => true]);

        $this->actingAs($user)->patch(route('member-profile.update'), [
            'display_name' => $user->profile->display_name,
            'bio' => 'Historique conservé.',
            'visit_frequency' => VisitFrequency::Often->value,
            'visibility' => ProfileVisibility::Visible->value,
            'interest_ids' => [],
        ])->assertRedirect(route('member-profile.show'));

        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $user->profile->id,
            'interest_id' => $legacyInactive->id,
            'is_selected' => true,
        ]);
    }

    public function test_profile_show_receives_only_effective_active_interests(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        [$active, $inactive, $suspended] = Interest::factory()->count(3)->create();
        $inactive->update(['is_active' => false]);
        $user = User::factory()->withProfile()->create();
        $user->profile->interestHistory()->attach([
            $active->id => ['is_selected' => true],
            $inactive->id => ['is_selected' => true],
            $suspended->id => ['is_selected' => false],
        ]);

        $this->actingAs($user)
            ->get(route('member-profile.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('profile/Show')
                ->where('profile.interests', [
                    ['id' => $active->id, 'name' => $active->name],
                ]));
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
