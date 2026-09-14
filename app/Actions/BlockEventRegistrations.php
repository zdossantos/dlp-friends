<?php

namespace App\Actions;

use App\Enums\EventRegistrationStatus;
use App\Events\EventChatAccessChanged;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class BlockEventRegistrations
{
    public function handle(User $first, User $second): void
    {
        $revoked = [];
        $events = Event::query()
            ->where(function (Builder $query) use ($first, $second): void {
                $query->where(fn (Builder $firstOrganizes) => $firstOrganizes
                    ->where('organizer_user_id', $first->id)
                    ->whereHas('registrations', fn (Builder $registrations) => $registrations
                        ->where('user_id', $second->id)
                        ->whereIn('status', [EventRegistrationStatus::Pending, EventRegistrationStatus::Accepted])))
                    ->orWhere(fn (Builder $secondOrganizes) => $secondOrganizes
                        ->where('organizer_user_id', $second->id)
                        ->whereHas('registrations', fn (Builder $registrations) => $registrations
                            ->where('user_id', $first->id)
                            ->whereIn('status', [EventRegistrationStatus::Pending, EventRegistrationStatus::Accepted])));
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($events as $event) {
            $participantId = $event->organizer_user_id === $first->id ? $second->id : $first->id;
            $hadAccess = $event->registrations()
                ->where('user_id', $participantId)
                ->where('status', EventRegistrationStatus::Accepted)
                ->exists();
            $event->registrations()
                ->where('user_id', $participantId)
                ->whereIn('status', [EventRegistrationStatus::Pending, EventRegistrationStatus::Accepted])
                ->update(['status' => EventRegistrationStatus::Blocked]);

            if ($hadAccess) {
                $revoked[] = [$event->id, $participantId];
            }
        }

        foreach ($revoked as [$eventId, $participantId]) {
            EventChatAccessChanged::dispatch($eventId, $participantId, 'revoked');
        }
    }
}
