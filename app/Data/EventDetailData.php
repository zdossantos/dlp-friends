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
            ->with('user.profile.avatar')
            ->get()
            ->map(fn ($registration): array => self::participant($registration->user));

        $privateData = [
            'detailedLocation' => $event->detailed_location,
            'participants' => collect([self::participant($event->organizer)])
                ->concat($accepted)
                ->values()
                ->all(),
        ];

        if ($event->organizer_user_id === $viewer->id) {
            $privateData['registrations'] = $event->registrations()
                ->with('user.profile.avatar')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($registration): array => [
                    'registrationId' => $registration->id,
                    'id' => $registration->user->id,
                    'displayName' => $registration->user->profile?->display_name,
                    'avatar' => self::avatar($registration->user),
                    'status' => $registration->status->value,
                ])->all();
        }

        return $data + $privateData;
    }

    /** @return array<string, mixed> */
    private static function participant(User $user): array
    {
        return [
            'id' => $user->id,
            'displayName' => $user->profile?->display_name,
            'avatar' => self::avatar($user),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function avatar(User $user): ?array
    {
        $avatar = $user->profile?->avatar;

        if ($avatar === null) {
            return null;
        }

        return [
            'id' => $avatar->id,
            'name' => $avatar->name,
            'image_url' => route('avatars.image', $avatar),
            'primary_color' => $avatar->primary_color,
            'secondary_color' => $avatar->secondary_color,
        ];
    }
}
