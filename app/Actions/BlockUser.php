<?php

namespace App\Actions;

use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BlockUser
{
    public function handle(User $blocker, User $blocked): Block
    {
        if ($blocker->is($blocked)) {
            throw ValidationException::withMessages([
                'member' => __('blocking.unavailable'),
            ]);
        }

        return DB::transaction(function () use ($blocker, $blocked): Block {
            [$lowId, $highId] = $blocker->id < $blocked->id
                ? [$blocker->id, $blocked->id]
                : [$blocked->id, $blocker->id];

            User::query()
                ->whereKey([$lowId, $highId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

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

            return $block;
        });
    }
}
