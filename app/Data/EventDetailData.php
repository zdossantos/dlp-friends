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

        $privateData = [
            'detailedLocation' => $event->detailed_location,
            'participants' => collect([[
                'id' => $event->organizer->id,
                'displayName' => $event->organizer->profile?->display_name,
            ]])->concat($accepted)->values()->all(),
        ];

        if ($event->organizer_user_id === $viewer->id) {
            $privateData['registrations'] = $event->registrations()
                ->with('user.profile')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($registration): array => [
                    'registrationId' => $registration->id,
                    'id' => $registration->user->id,
                    'displayName' => $registration->user->profile?->display_name,
                    'status' => $registration->status->value,
                ])->all();
        }

        return $data + $privateData;
    }
}
