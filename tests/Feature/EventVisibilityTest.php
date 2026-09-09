<?php

namespace Tests\Feature;

use App\Enums\EventRegistrationStatus;
use App\Models\Block;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_only_lists_upcoming_active_events_without_blocked_organizers(): void
    {
        $viewer = User::factory()->withProfile()->create();
        $visible = Event::factory()->create(['title' => 'Visible']);
        Event::factory()->create(['title' => 'Past', 'starts_at' => now()->subMinute()]);
        Event::factory()->create(['title' => 'Cancelled', 'cancelled_at' => now()]);
        $blocked = Event::factory()->create(['title' => 'Blocked']);
        Block::factory()->create([
            'blocker_user_id' => $blocked->organizer_user_id,
            'blocked_user_id' => $viewer->id,
        ]);

        $this->actingAs($viewer)->get(route('events.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Index', false)
                ->has('events', 1)
                ->where('events.0.id', $visible->id)
                ->missing('events.0.detailedLocation')
                ->missing('events.0.participants'));
    }

    public function test_only_the_organizer_and_accepted_members_receive_private_event_details(): void
    {
        $event = Event::factory()->create();
        $accepted = User::factory()->withProfile()->create();
        $pending = User::factory()->withProfile()->create();
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $accepted->id,
            'status' => EventRegistrationStatus::Accepted,
        ]);
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $pending->id,
            'status' => EventRegistrationStatus::Pending,
        ]);

        foreach ([$event->organizer, $accepted] as $authorized) {
            $this->actingAs($authorized)->get(route('events.show', $event))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('event.detailedLocation', $event->detailed_location)
                    ->has('event.participants', 2));
        }

        $this->actingAs($pending)->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('event.detailedLocation')
                ->missing('event.participants'));
    }

    public function test_my_events_keeps_cancelled_and_started_events(): void
    {
        $member = User::factory()->withProfile()->create();
        Event::factory()->create([
            'organizer_user_id' => $member->id,
            'starts_at' => now()->subDay(),
            'cancelled_at' => now()->subDays(2),
        ]);

        $this->actingAs($member)->get(route('events.mine'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('events', 1));
    }
}
