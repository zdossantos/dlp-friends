<?php

namespace Tests\Feature;

use App\Enums\EventRegistrationStatus;
use App\Enums\ProfileVisibility;
use App\Enums\SwipeDecision;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberMatch;
use App\Models\Swipe;
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

    public function test_the_participant_list_identifies_the_current_member(): void
    {
        $event = Event::factory()->create();
        $viewer = User::factory()->withProfile()->create();
        EventRegistration::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $viewer->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('events.participants.index', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.event.participants.1.id', $viewer->id)
                ->where('panel.event.participants.1.isSelf', true));
    }

    public function test_a_previously_passed_participant_can_be_liked_from_the_event(): void
    {
        $event = Event::factory()->create();
        $viewer = User::factory()->withProfile()->create();
        EventRegistration::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $viewer->id,
        ]);
        Swipe::factory()->create([
            'actor_user_id' => $viewer->id,
            'target_user_id' => $event->organizer->id,
            'decision' => SwipeDecision::Pass,
        ]);

        $this->actingAs($viewer)
            ->get(route('events.participants.show', [$event, $event->organizer]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.profile.canLike', true));
    }

    public function test_a_matched_participant_exposes_the_existing_conversation(): void
    {
        $event = Event::factory()->create();
        $viewer = User::factory()->withProfile()->create();
        EventRegistration::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $viewer->id,
        ]);
        [$lowId, $highId] = collect([$viewer->id, $event->organizer->id])->sort()->values()->all();
        $match = MemberMatch::factory()->create([
            'user_low_id' => $lowId,
            'user_high_id' => $highId,
        ]);
        $conversation = Conversation::query()->create(['match_id' => $match->id]);

        $this->actingAs($viewer)
            ->get(route('events.participants.show', [$event, $event->organizer]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.profile.conversationHref', route('conversations.show', $conversation, absolute: false))
                ->where('panel.profile.canLike', false));
    }

    public function test_a_blocked_participant_opens_a_redacted_profile_with_only_the_available_unblock_action(): void
    {
        $event = Event::factory()->create();
        $viewer = User::factory()->withProfile()->create();
        $blocked = User::factory()->withProfile()->create();
        foreach ([$viewer, $blocked] as $participant) {
            EventRegistration::factory()->accepted()->create([
                'event_id' => $event->id,
                'user_id' => $participant->id,
            ]);
        }
        Block::factory()->create([
            'blocker_user_id' => $viewer->id,
            'blocked_user_id' => $blocked->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('events.participants.index', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.event.participants.2.id', $blocked->id)
                ->where('panel.event.participants.2.isBlocked', true)
                ->where('panel.event.participants.2.canUnblock', true)
                ->where('panel.event.participants.2.displayName', null)
                ->where('panel.event.participants.2.avatar', null));

        $this->actingAs($viewer)
            ->get(route('events.participants.show', [$event, $blocked]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.kind', 'participant-profile')
                ->where('panel.profile.isBlocked', true)
                ->where('panel.profile.member.id', $blocked->id)
                ->missing('panel.profile.member.display_name')
                ->missing('panel.profile.member.avatar')
                ->missing('panel.profile.member.bio')
                ->where('panel.profile.canUnblock', true)
                ->where('panel.profile.canLike', false)
                ->where('panel.profile.canBlock', false)
                ->where('panel.profile.conversationHref', null));
    }

    public function test_a_non_participant_profile_is_not_exposed_by_an_event_route(): void
    {
        $event = Event::factory()->create();
        $unrelated = User::factory()->withProfile()->create();

        $this->actingAs($event->organizer)
            ->get(route('events.participants.show', [$event, $unrelated]))
            ->assertNotFound();
    }

    public function test_an_unavailable_participant_profile_returns_to_the_participant_list(): void
    {
        $event = Event::factory()->create();
        $accepted = User::factory()->withProfile()->create();
        EventRegistration::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $accepted->id,
        ]);
        $event->organizer->profile?->update(['visibility' => ProfileVisibility::Hidden]);

        $this->actingAs($accepted)
            ->get(route('events.participants.show', [
                'event' => $event,
                'member' => $event->organizer,
                'origin' => 'mine',
            ]))
            ->assertRedirect(route('events.participants.index', [
                'event' => $event,
                'origin' => 'mine',
            ], absolute: false));
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

    public function test_blocked_registrations_do_not_expose_who_blocked_the_organizer(): void
    {
        $event = Event::factory()->create();
        $member = User::factory()->withProfile()->create();
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => EventRegistrationStatus::Blocked,
        ]);

        $this->actingAs($event->organizer)
            ->get(route('events.registrations.index', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('panel.kind', 'registrations')
                ->has('panel.event.registrations', 0));
    }
}
