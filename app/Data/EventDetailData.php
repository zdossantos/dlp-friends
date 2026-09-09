<?php

namespace App\Data;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;

final readonly class EventDetailData
{
    /** @return array<string, mixed> */
    public static function from(Event $event, User $viewer): array
    {
        $data = EventSummaryData::from($event, $viewer);

        if (! (new EventPolicy)->viewPrivateDetails($viewer, $event)) {
            return $data;
        }

        $accepted = $event->registrations()
            ->where('status', EventRegistrationStatus::Accepted)
            ->with('user.profile')
            ->get()
            ->map(fn ($registration): array => [
                'id' => $registration->user->id,
                'displayName' => $registration->user->profile?->display_name,
            ]);

        return $data + [
            'detailedLocation' => $event->detailed_location,
            'participants' => collect([[
                'id' => $event->organizer->id,
                'displayName' => $event->organizer->profile?->display_name,
            ]])->concat($accepted)->values()->all(),
        ];
    }
}
