<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventParticipantPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_organizer_and_accepted_members_can_open_the_participant_panel(): void
    {
        $event = Event::factory()->create();
        $accepted = User::factory()->withProfile()->create();
        EventRegistration::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $accepted->id,
        ]);

        foreach ([$event->organizer, $accepted] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('events.participants.index', ['event' => $event, 'origin' => 'mine']))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Events/Mine')
                    ->where('panel.kind', 'participants')
                    ->has('panel.event.participants', 2)
                    ->has('panel.event.participants.0.avatar.image_url'));
        }
    }

    public function test_pending_and_unregistered_members_cannot_open_private_participants(): void
    {
        $event = Event::factory()->create();
        $pending = User::factory()->withProfile()->create();
        $outsider = User::factory()->withProfile()->create();
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $pending->id,
        ]);

        $this->actingAs($pending)
            ->get(route('events.participants.index', $event))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->get(route('events.participants.index', $event))
            ->assertForbidden();
    }

    public function test_a_participant_profile_opens_inside_the_same_workspace(): void
    {
        $event = Event::factory()->create();
        $accepted = User::factory()->withProfile()->create();
        EventRegistration::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $accepted->id,
        ]);

        $this->actingAs($accepted)
            ->get(route('events.participants.show', [$event, $event->organizer]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Index')
                ->where('panel.kind', 'participant-profile')
                ->where('panel.profile.member.id', $event->organizer->id)
                ->has('panel.profile.member.avatar')
                ->where('panel.event.id', $event->id));
    }

    public function test_a_member_can_open_their_own_participant_profile_without_social_actions(): void
    {
        $event = Event::factory()->create();
        $accepted = User::factory()->withProfile()->create();
        EventRegistration::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $accepted->id,
        ]);

        $this->actingAs($accepted)
            ->get(route('events.participants.show', [$event, $accepted]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.profile.member.id', $accepted->id)
                ->where('panel.profile.canLike', false)
                ->where('panel.profile.canBlock', false)
                ->where('panel.profile.canUnblock', false));
    }

    public function test_a_non_participant_profile_is_not_exposed_by_an_event_route(): void
    {
        $event = Event::factory()->create();
        $unrelated = User::factory()->withProfile()->create();

        $this->actingAs($event->organizer)
            ->get(route('events.participants.show', [$event, $unrelated]))
            ->assertNotFound();
    }

    public function test_only_the_organizer_can_open_registration_management(): void
    {
        $event = Event::factory()->create();
        $accepted = User::factory()->withProfile()->create();
        EventRegistration::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $accepted->id,
        ]);

        $this->actingAs($event->organizer)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.kind', 'registrations')
                ->has('panel.event.registrations', 1));

        $this->actingAs($accepted)
            ->get(route('events.registrations.index', $event))
            ->assertForbidden();
    }
}
