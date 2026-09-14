<?php

namespace App\Actions;

use App\Enums\EventNotificationType;
use App\Enums\EventRegistrationStatus;
use App\Events\EventChatAccessChanged;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Notifications\EventLifecycleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RemoveEventParticipant
{
    public function handle(User $organizer, EventRegistration $registration): EventRegistration
    {
        $hadAccess = false;
        $removedRegistration = DB::transaction(function () use ($organizer, $registration, &$hadAccess): EventRegistration {
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

            $hadAccess = $lockedRegistration->status === EventRegistrationStatus::Accepted;
            $lockedRegistration->update(['status' => EventRegistrationStatus::Removed]);

            return $lockedRegistration->refresh();
        });

        $removedRegistration->user->notify(new EventLifecycleNotification(
            $removedRegistration->event,
            EventNotificationType::Removed,
        ));

        if ($hadAccess) {
            EventChatAccessChanged::dispatch(
                $removedRegistration->event_id,
                $removedRegistration->user_id,
                'revoked',
            );
        }

        return $removedRegistration;
    }

    private function fail(string $key): never
    {
        throw ValidationException::withMessages(['registration' => __($key)]);
    }
}
