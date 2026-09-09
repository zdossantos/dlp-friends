<?php

namespace App\Actions;

use App\Enums\EventNotificationType;
use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Notifications\EventLifecycleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DecideEventRegistration
{
    public function handle(User $organizer, EventRegistration $registration, bool $accept): EventRegistration
    {
        $decidedRegistration = DB::transaction(function () use ($organizer, $registration, $accept): EventRegistration {
            $event = Event::query()->lockForUpdate()->findOrFail($registration->event_id);
            $lockedRegistration = EventRegistration::query()->lockForUpdate()->findOrFail($registration->id);

            if ($event->organizer_user_id !== $organizer->id) {
                $this->fail('events.errors.organizer_only');
            }

            if ($event->cancelled_at !== null || $event->hasStarted()) {
                $this->fail('events.errors.event_unavailable');
            }

            if ($lockedRegistration->status !== EventRegistrationStatus::Pending) {
                $this->fail('events.errors.registration_terminal');
            }

            if ($accept && $event->occupiedPlaces() >= $event->capacity) {
                $this->fail('events.errors.event_full');
            }

            $lockedRegistration->update([
                'status' => $accept
                    ? EventRegistrationStatus::Accepted
                    : EventRegistrationStatus::Refused,
            ]);

            return $lockedRegistration->refresh();
        });

        $decidedRegistration->user->notify(new EventLifecycleNotification(
            $decidedRegistration->event,
            $accept ? EventNotificationType::Accepted : EventNotificationType::Refused,
        ));

        return $decidedRegistration;
    }

    private function fail(string $key): never
    {
        throw ValidationException::withMessages(['registration' => __($key)]);
    }
}
