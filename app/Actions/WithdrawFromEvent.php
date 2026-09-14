<?php

namespace App\Actions;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WithdrawFromEvent
{
    public function handle(User $member, Event $event): EventRegistration
    {
        return DB::transaction(function () use ($member, $event): EventRegistration {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            $registration = EventRegistration::query()
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $member->id)
                ->lockForUpdate()
                ->first();

            if ($lockedEvent->hasStarted() || $registration === null
                || ! in_array($registration->status, [
                    EventRegistrationStatus::Pending,
                    EventRegistrationStatus::Accepted,
                ], true)) {
                throw ValidationException::withMessages([
                    'registration' => __('events.errors.withdrawal_unavailable'),
                ]);
            }

            $registration->update(['status' => EventRegistrationStatus::Withdrawn]);

            return $registration->refresh();
        });
    }
}
