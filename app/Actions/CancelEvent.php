<?php

namespace App\Actions;

use App\Enums\EventNotificationType;
use App\Enums\EventRegistrationStatus;
use App\Events\EventChatAccessChanged;
use App\Models\Event;
use App\Models\User;
use App\Notifications\EventLifecycleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelEvent
{
    public function handle(User $organizer, Event $event): Event
    {
        $didCancel = false;
        $cancelledEvent = DB::transaction(function () use ($organizer, $event, &$didCancel): Event {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($lockedEvent->organizer_user_id !== $organizer->id || $lockedEvent->hasStarted()) {
                throw ValidationException::withMessages([
                    'event' => __('events.errors.cancellation_unavailable'),
                ]);
            }

            if ($lockedEvent->cancelled_at === null) {
                $lockedEvent->update(['cancelled_at' => now()]);
                $didCancel = true;
            }

            return $lockedEvent->refresh();
        });

        if ($didCancel) {
            $acceptedUserIds = $cancelledEvent->registrations()
                ->where('status', EventRegistrationStatus::Accepted)
                ->pluck('user_id');
            $cancelledEvent->registrations()
                ->whereIn('status', [EventRegistrationStatus::Pending, EventRegistrationStatus::Accepted])
                ->with('user')
                ->get()
                ->each(fn ($registration) => $registration->user->notify(
                    new EventLifecycleNotification($cancelledEvent, EventNotificationType::Cancelled),
                ));

            collect([$cancelledEvent->organizer_user_id])
                ->concat($acceptedUserIds)
                ->unique()
                ->each(fn (int $userId) => EventChatAccessChanged::dispatch(
                    $cancelledEvent->id,
                    $userId,
                    'read_only',
                ));
        }

        return $cancelledEvent;
    }
}
