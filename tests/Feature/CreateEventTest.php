<?php

namespace Tests\Feature;

use App\Enums\EventRegistrationMode;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_complete_member_can_create_an_event_from_a_paris_local_datetime(): void
    {
        $this->travelTo('2026-09-10 10:00:00');
        $organizer = User::factory()->withProfile()->create();

        $response = $this->actingAs($organizer)->post(route('events.store'), [
            'title' => 'Une journée entre amis',
            'description' => 'Retrouvons-nous pour profiter du parc.',
            'general_location' => 'Disneyland Park',
            'detailed_location' => 'Sous l’horloge de Main Street Station',
            'starts_at' => '2026-10-10T10:30',
            'capacity' => 4,
            'registration_mode' => EventRegistrationMode::Automatic->value,
        ]);

        $event = Event::query()->firstOrFail();

        $response->assertRedirect(route('events.show', $event));
        $this->assertTrue($event->organizer->is($organizer));
        $this->assertSame('2026-10-10 08:30', $event->starts_at->utc()->format('Y-m-d H:i'));
    }

    public function test_event_creation_requires_valid_bounded_data(): void
    {
        $member = User::factory()->withProfile()->create();

        $this->actingAs($member)->post(route('events.store'), [
            'title' => '',
            'description' => '',
            'general_location' => '',
            'detailed_location' => '',
            'starts_at' => now()->subDay()->format('Y-m-d\TH:i'),
            'capacity' => 0,
            'registration_mode' => 'instant',
        ])->assertSessionHasErrors([
            'title', 'description', 'general_location', 'detailed_location',
            'starts_at', 'capacity', 'registration_mode',
        ]);

        $this->assertDatabaseEmpty('events');
    }

    public function test_guests_and_incomplete_members_cannot_access_event_creation(): void
    {
        $this->get(route('events.create'))->assertRedirect(route('login'));

        $incomplete = User::factory()->withProfile(false)->create();

        $this->actingAs($incomplete)
            ->get(route('events.create'))
            ->assertRedirect(route('onboarding.show'));
    }
}
