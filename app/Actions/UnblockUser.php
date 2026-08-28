<?php

namespace App\Actions;

use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UnblockUser
{
    public function handle(User $blocker, User $blocked): void
    {
        DB::transaction(function () use ($blocker, $blocked): void {
            [$lowId, $highId] = $blocker->id < $blocked->id
                ? [$blocker->id, $blocked->id]
                : [$blocked->id, $blocker->id];

            User::query()
                ->whereKey([$lowId, $highId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $deleted = Block::query()
                ->where('blocker_user_id', $blocker->id)
                ->where('blocked_user_id', $blocked->id)
                ->delete();

            if ($deleted === 0 || $blocker->hasBlockedRelationshipWith($blocked)) {
                return;
            }

            $match = MemberMatch::query()
                ->where('user_low_id', $lowId)
                ->where('user_high_id', $highId)
                ->first();

            $match?->conversation()->update(['archived_at' => null]);
        });
    }
}
