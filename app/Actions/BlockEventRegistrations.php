<?php

namespace App\Actions;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class BlockEventRegistrations
{
    public function handle(User $first, User $second): void
    {
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
            $event->registrations()
                ->where('user_id', $participantId)
                ->whereIn('status', [EventRegistrationStatus::Pending, EventRegistrationStatus::Accepted])
                ->update(['status' => EventRegistrationStatus::Blocked]);
        }
    }
}
