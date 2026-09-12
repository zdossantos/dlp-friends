<?php

namespace Tests\Feature;

use App\Actions\CancelEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CancelEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_organizer_can_cancel_an_event_before_it_starts_idempotently(): void
    {
        $event = Event::factory()->create();

        try {
            app(CancelEvent::class)->handle(User::factory()->create(), $event);
            $this->fail('Only the organizer should cancel an event.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        app(CancelEvent::class)->handle($event->organizer, $event);
        $cancelledAt = $event->refresh()->cancelled_at;
        app(CancelEvent::class)->handle($event->organizer, $event);

        $this->assertNotNull($cancelledAt);
        $this->assertTrue($cancelledAt->equalTo($event->refresh()->cancelled_at));
    }
}
