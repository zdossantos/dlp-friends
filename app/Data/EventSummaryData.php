<?php

namespace App\Data;

use App\Models\Event;
use App\Models\User;

final readonly class EventSummaryData
{
    /** @return array<string, mixed> */
    public static function from(Event $event, User $viewer): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'generalLocation' => $event->general_location,
            'startsAt' => $event->starts_at->toIso8601String(),
            'capacity' => $event->capacity,
            'occupiedPlaces' => $event->occupiedPlaces(),
            'registrationMode' => $event->registration_mode->value,
            'isCancelled' => $event->cancelled_at !== null,
            'isStarted' => $event->hasStarted(),
            'isOrganizer' => $event->organizer_user_id === $viewer->id,
            'registrationStatus' => $event->registrations()
                ->where('user_id', $viewer->id)
                ->first()?->status->value,
        ];
    }
}
