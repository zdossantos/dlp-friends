<?php

namespace App\Actions;

use App\Enums\EventRegistrationStatus;
use App\Events\EventChatAccessChanged;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WithdrawFromEvent
{
    public function handle(User $member, Event $event): EventRegistration
    {
        $hadAccess = false;
        $registration = DB::transaction(function () use ($member, $event, &$hadAccess): EventRegistration {
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

            $hadAccess = $registration->status === EventRegistrationStatus::Accepted;
            $registration->update(['status' => EventRegistrationStatus::Withdrawn]);

            return $registration->refresh();
        });

        if ($hadAccess) {
            EventChatAccessChanged::dispatch($event->id, $member->id, 'revoked');
        }

        return $registration;
    }
}
