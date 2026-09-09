<?php

namespace Tests\Unit\Models;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_organizer_and_accepted_members_occupy_places(): void
    {
        $event = Event::factory()->create();

        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status' => EventRegistrationStatus::Accepted,
        ]);
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status' => EventRegistrationStatus::Pending,
        ]);

        $this->assertSame(2, $event->occupiedPlaces());
    }

    public function test_major_changes_are_allowed_exactly_twenty_four_hours_before_start(): void
    {
        $this->travelTo('2026-09-10 10:00:00');
        $event = Event::factory()->create(['starts_at' => now()->addDay()]);

        $this->assertTrue($event->majorChangesAllowed());

        $this->travel(1)->second();

        $this->assertFalse($event->majorChangesAllowed());
    }

    public function test_an_event_is_started_from_its_start_time(): void
    {
        $this->travelTo('2026-09-10 10:00:00');

        $futureEvent = Event::factory()->create(['starts_at' => now()->addSecond()]);
        $startedEvent = Event::factory()->create(['starts_at' => now()]);

        $this->assertFalse($futureEvent->hasStarted());
        $this->assertTrue($startedEvent->hasStarted());
    }

    public function test_user_event_relations_are_available(): void
    {
        $organizer = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->create(['organizer_user_id' => $organizer->id]);
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
        ]);

        $this->assertTrue($organizer->organizedEvents->contains($event));
        $this->assertTrue($participant->eventRegistrations->contains('event_id', $event->id));
    }
}
