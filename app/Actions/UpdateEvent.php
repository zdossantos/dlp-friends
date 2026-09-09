<?php

namespace App\Actions;

use App\Enums\EventNotificationType;
use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\User;
use App\Notifications\EventLifecycleNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateEvent
{
    /** @param array<string, mixed> $validated */
    public function handle(User $organizer, Event $event, array $validated): Event
    {
        $shouldNotify = false;
        $updatedEvent = DB::transaction(function () use ($organizer, $event, $validated, &$shouldNotify): Event {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($lockedEvent->organizer_user_id !== $organizer->id || $lockedEvent->hasStarted()) {
                $this->fail('events.errors.update_unavailable');
            }

            $validated['starts_at'] = CarbonImmutable::createFromFormat(
                'Y-m-d\TH:i',
                (string) $validated['starts_at'],
                'Europe/Paris',
            )->utc();

            $majorFields = ['starts_at', 'general_location', 'detailed_location', 'capacity'];
            $majorChanged = collect($majorFields)->contains(
                fn (string $field): bool => $this->valuesDiffer($lockedEvent, $validated, $field),
            );
            if ($majorChanged && ! $lockedEvent->majorChangesAllowed()) {
                $this->fail('events.errors.major_change_cutoff');
            }

            if ($lockedEvent->registration_mode->value !== $validated['registration_mode']
                && $lockedEvent->registrations()->exists()) {
                $this->fail('events.errors.mode_locked');
            }

            if ((int) $validated['capacity'] < $lockedEvent->occupiedPlaces()) {
                $this->fail('events.errors.capacity_below_occupied');
            }

            $shouldNotify = collect(['starts_at', 'general_location', 'detailed_location'])->contains(
                fn (string $field): bool => $this->valuesDiffer($lockedEvent, $validated, $field),
            );
            $lockedEvent->update($validated);

            return $lockedEvent->refresh();
        });

        if ($shouldNotify) {
            $this->notifyMembers($updatedEvent, EventNotificationType::Changed);
        }

        return $updatedEvent;
    }

    private function notifyMembers(Event $event, EventNotificationType $type): void
    {
        $event->registrations()
            ->whereIn('status', [EventRegistrationStatus::Pending, EventRegistrationStatus::Accepted])
            ->with('user')
            ->get()
            ->each(fn ($registration) => $registration->user->notify(
                new EventLifecycleNotification($event, $type),
            ));
    }

    /** @param array<string, mixed> $validated */
    private function valuesDiffer(Event $event, array $validated, string $field): bool
    {
        if ($field === 'starts_at') {
            return ! $event->starts_at->startOfMinute()->equalTo($validated[$field]);
        }

        return $event->getAttribute($field) != $validated[$field];
    }

    private function fail(string $key): never
    {
        throw ValidationException::withMessages(['event' => __($key)]);
    }
}
