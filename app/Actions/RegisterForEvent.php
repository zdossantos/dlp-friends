<?php

namespace App\Actions;

use App\Enums\EventRegistrationMode;
use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegisterForEvent
{
    public function handle(User $member, Event $event): EventRegistration
    {
        return DB::transaction(function () use ($member, $event): EventRegistration {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            $this->ensureAvailable($member, $lockedEvent);

            $registration = EventRegistration::query()
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $member->id)
                ->lockForUpdate()
                ->first();

            if ($registration !== null && $registration->status !== EventRegistrationStatus::Withdrawn) {
                $this->fail('events.errors.registration_terminal');
            }

            $status = $lockedEvent->registration_mode === EventRegistrationMode::Automatic
                ? EventRegistrationStatus::Accepted
                : EventRegistrationStatus::Pending;

            if ($status === EventRegistrationStatus::Accepted
                && $lockedEvent->occupiedPlaces() >= $lockedEvent->capacity) {
                $this->fail('events.errors.event_full');
            }

            if ($registration !== null) {
                $registration->update(['status' => $status]);

                return $registration->refresh();
            }

            return $lockedEvent->registrations()->create([
                'user_id' => $member->id,
                'status' => $status,
            ]);
        });
    }

    private function ensureAvailable(User $member, Event $event): void
    {
        if ($event->organizer_user_id === $member->id) {
            $this->fail('events.errors.organizer_registration');
        }

        if ($event->cancelled_at !== null || $event->hasStarted()) {
            $this->fail('events.errors.event_unavailable');
        }

        if ($member->hasBlockedRelationshipWith($event->organizer)) {
            $this->fail('events.errors.blocked');
        }
    }

    private function fail(string $key): never
    {
        throw ValidationException::withMessages(['registration' => __($key)]);
    }
}
