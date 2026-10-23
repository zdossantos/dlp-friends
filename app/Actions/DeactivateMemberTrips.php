<?php

namespace App\Actions;

use App\Enums\TripRegistrationStatus;
use App\Events\GroupChatAccessChanged;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class DeactivateMemberTrips
{
    // Account lifecycle callers already hold the member lock in their transaction.
    public function handle(User $member): void
    {
        $trips = Trip::query()->where(fn (Builder $query) => $query->where('organizer_user_id', $member->id)
            ->orWhereHas('registrations', fn (Builder $registrations) => $registrations->where('user_id', $member->id)->whereIn('status', [TripRegistrationStatus::Pending, TripRegistrationStatus::Accepted])))
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($trips as $trip) {
            $chat = $trip->chat()->first();
            if ($trip->organizer_user_id === $member->id) {
                if ($trip->departs_at->isFuture() && $trip->cancelled_at === null) {
                    $trip->update(['cancelled_at' => now(), 'cancellation_reason' => null]);
                }
                if ($chat !== null) {
                    foreach ($trip->registrations()->where('status', TripRegistrationStatus::Accepted)->pluck('user_id')->filter() as $userId) {
                        GroupChatAccessChanged::dispatch($chat->id, $userId, 'revoked');
                    }
                }
            } else {
                $registration = $trip->registrations()->where('user_id', $member->id)->lockForUpdate()->first();
                if ($registration === null) {
                    continue;
                }
                $registration->update(['status' => TripRegistrationStatus::Blocked, 'reason' => null, 'decided_at' => now()]);
                if ($chat !== null) {
                    GroupChatAccessChanged::dispatch($chat->id, $member->id, 'revoked');
                }
            }
        }
    }
}
