<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;

final class CreateEvent
{
    /** @param array<string, mixed> $validated */
    public function handle(User $organizer, array $validated): Event
    {
        $validated['starts_at'] = CarbonImmutable::createFromFormat(
            'Y-m-d\TH:i',
            (string) $validated['starts_at'],
            'Europe/Paris',
        )->utc();

        return $organizer->organizedEvents()->create($validated);
    }
}
