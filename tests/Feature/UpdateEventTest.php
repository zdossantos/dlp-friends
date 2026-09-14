<?php

namespace Tests\Feature;

use App\Actions\UpdateEvent;
use App\Enums\EventRegistrationMode;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_major_fields_can_change_exactly_twenty_four_hours_before_start(): void
    {
        $this->travelTo('2026-09-10 10:00:00');
        $event = Event::factory()->create(['starts_at' => now()->addDay()]);

        app(UpdateEvent::class)->handle($event->organizer, $event, [
            ...$this->attributes($event),
            'starts_at' => '2026-09-12T14:00',
            'general_location' => 'Walt Disney Studios Park',
        ]);

        $this->assertSame('Walt Disney Studios Park', $event->refresh()->general_location);
    }

    public function test_major_fields_use_the_persisted_start_for_the_cutoff(): void
    {
        $this->travelTo('2026-09-10 10:00:01');
        $event = Event::factory()->create(['starts_at' => '2026-09-11 10:00:00']);

        $this->expectException(ValidationException::class);
        app(UpdateEvent::class)->handle($event->organizer, $event, [
            ...$this->attributes($event),
            'starts_at' => '2026-09-20T12:00',
        ]);
    }

    public function test_title_and_description_can_change_until_start(): void
    {
        $event = Event::factory()->create(['starts_at' => now()->addMinute()]);

        app(UpdateEvent::class)->handle($event->organizer, $event, [
            ...$this->attributes($event),
            'title' => 'Nouveau titre',
            'description' => 'Nouvelle description',
        ]);

        $this->assertSame('Nouveau titre', $event->refresh()->title);
    }

    public function test_mode_is_locked_after_any_registration_and_capacity_cannot_drop_below_occupied(): void
    {
        $event = Event::factory()->create(['capacity' => 4]);
        EventRegistration::factory()->accepted()->create(['event_id' => $event->id]);

        try {
            app(UpdateEvent::class)->handle($event->organizer, $event, [
                ...$this->attributes($event),
                'registration_mode' => EventRegistrationMode::Manual->value,
            ]);
            $this->fail('The registration mode should be locked.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(ValidationException::class);
        app(UpdateEvent::class)->handle($event->organizer, $event, [
            ...$this->attributes($event),
            'capacity' => 1,
        ]);
    }

    /** @return array<string, mixed> */
    private function attributes(Event $event): array
    {
        return [
            'title' => $event->title,
            'description' => $event->description,
            'general_location' => $event->general_location,
            'detailed_location' => $event->detailed_location,
            'starts_at' => $event->starts_at->setTimezone('Europe/Paris')->format('Y-m-d\TH:i'),
            'capacity' => $event->capacity,
            'registration_mode' => $event->registration_mode->value,
        ];
    }
}
