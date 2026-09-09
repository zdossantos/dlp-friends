<?php

namespace App\Actions;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RemoveEventParticipant
{
    public function handle(User $organizer, EventRegistration $registration): EventRegistration
    {
        return DB::transaction(function () use ($organizer, $registration): EventRegistration {
            $event = Event::query()->lockForUpdate()->findOrFail($registration->event_id);
            $lockedRegistration = EventRegistration::query()->lockForUpdate()->findOrFail($registration->id);

            if ($event->organizer_user_id !== $organizer->id) {
                $this->fail('events.errors.organizer_only');
            }

            if ($event->hasStarted() || ! in_array($lockedRegistration->status, [
                EventRegistrationStatus::Pending,
                EventRegistrationStatus::Accepted,
            ], true)) {
                $this->fail('events.errors.registration_terminal');
            }

            $lockedRegistration->update(['status' => EventRegistrationStatus::Removed]);

            return $lockedRegistration->refresh();
        });
    }

    private function fail(string $key): never
    {
        throw ValidationException::withMessages(['registration' => __($key)]);
    }
}
