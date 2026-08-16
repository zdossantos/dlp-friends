<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
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
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('member-profile.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('profile/Create')
                ->has('visitFrequencies', 4)
                ->has('visibilities', 2));
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
}
