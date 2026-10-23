<?php

namespace App\Actions;

use App\Enums\RoleName;
use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BlockUser
{
    public function __construct(private BlockEventRegistrations $blockEventRegistrations) {}

    public function handle(User $blocker, User $blocked, bool $confirmTripDepartures = false): Block
    {
        if ($blocker->is($blocked) || $blocked->loadMissing('roles')->hasRole(RoleName::Admin)) {
            throw ValidationException::withMessages([
                'member' => __('blocking.unavailable'),
            ]);
        }

        return DB::transaction(function () use ($blocker, $blocked, $confirmTripDepartures): Block {
            [$lowId, $highId] = $blocker->id < $blocked->id
                ? [$blocker->id, $blocked->id]
                : [$blocked->id, $blocker->id];

            $lockedBlocked = User::query()
                ->whereKey([$lowId, $highId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->firstWhere('id', $blocked->id);

            if (! $lockedBlocked instanceof User
                || $lockedBlocked->load('roles')->hasRole(RoleName::Admin)) {
                throw ValidationException::withMessages([
                    'member' => __('blocking.unavailable'),
                ]);
            }

            $trips = app(BlockTripRegistrations::class);
            if (! $confirmTripDepartures && $trips->requiresDepartureConfirmation($blocker, $blocked)) {
                throw ValidationException::withMessages(['confirm_trip_departures' => __('blocking.trip_departures')]);
            }
            $trips->handle($blocker, $blocked);

            $block = Block::query()->firstOrCreate([
                'blocker_user_id' => $blocker->id,
                'blocked_user_id' => $blocked->id,
            ]);

            $match = MemberMatch::query()
                ->where('user_low_id', $lowId)
                ->where('user_high_id', $highId)
                ->first();

            $match?->conversation()
                ->whereNull('archived_at')
                ->update(['archived_at' => now()]);

            $this->blockEventRegistrations->handle($blocker, $blocked);

            return $block;
        }, 3);
    }
}
