<?php

namespace Tests\Feature;

use App\Actions\CancelEvent;
use App\Actions\DecideEventRegistration;
use App\Actions\RemoveEventParticipant;
use App\Actions\UpdateEvent;
use App\Enums\EventNotificationType;
use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\EventLifecycleNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EventNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_or_date_changes_notify_pending_and_accepted_members_without_private_location(): void
    {
        Notification::fake();
        $event = Event::factory()->create();
        $accepted = EventRegistration::factory()->accepted()->create(['event_id' => $event->id]);
        $pending = EventRegistration::factory()->create(['event_id' => $event->id]);
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'status' => EventRegistrationStatus::Refused,
        ]);

        app(UpdateEvent::class)->handle($event->organizer, $event, [
            'title' => $event->title,
            'description' => $event->description,
            'general_location' => 'Walt Disney Studios Park',
            'detailed_location' => 'Lieu privé secret',
            'starts_at' => $event->starts_at->setTimezone('Europe/Paris')->format('Y-m-d\TH:i'),
            'capacity' => $event->capacity,
            'registration_mode' => $event->registration_mode->value,
        ]);

        Notification::assertSentTo([$accepted->user, $pending->user], EventLifecycleNotification::class,
            function (EventLifecycleNotification $notification): bool {
                $data = $notification->toArray($notification->event->organizer);

                return $notification->type === EventNotificationType::Changed
                    && ! str_contains(json_encode($data, JSON_THROW_ON_ERROR), 'Lieu privé secret');
            });
        Notification::assertCount(2);
    }

    public function test_cancellation_notifies_pending_and_accepted_members_without_changing_statuses(): void
    {
        Notification::fake();
        $event = Event::factory()->create();
        $accepted = EventRegistration::factory()->accepted()->create(['event_id' => $event->id]);
        $pending = EventRegistration::factory()->create(['event_id' => $event->id]);

        app(CancelEvent::class)->handle($event->organizer, $event);

        Notification::assertSentTo([$accepted->user, $pending->user], EventLifecycleNotification::class,
            fn (EventLifecycleNotification $notification): bool => $notification->type === EventNotificationType::Cancelled);
        $this->assertSame(EventRegistrationStatus::Accepted, $accepted->refresh()->status);
        $this->assertSame(EventRegistrationStatus::Pending, $pending->refresh()->status);
    }

    public function test_decisions_and_removal_notify_the_concerned_member(): void
    {
        Notification::fake();
        $accepted = EventRegistration::factory()->create();
        $refused = EventRegistration::factory()->create();
        $removed = EventRegistration::factory()->accepted()->create();

        app(DecideEventRegistration::class)->handle($accepted->event->organizer, $accepted, true);
        app(DecideEventRegistration::class)->handle($refused->event->organizer, $refused, false);
        app(RemoveEventParticipant::class)->handle($removed->event->organizer, $removed);

        foreach ([
            [$accepted, EventNotificationType::Accepted],
            [$refused, EventNotificationType::Refused],
            [$removed, EventNotificationType::Removed],
        ] as [$registration, $type]) {
            Notification::assertSentTo($registration->user, EventLifecycleNotification::class,
                fn (EventLifecycleNotification $notification): bool => $notification->type === $type);
        }
    }
}
