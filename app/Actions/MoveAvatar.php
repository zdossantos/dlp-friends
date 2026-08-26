<?php

namespace App\Actions;

use App\Models\Avatar;
use Illuminate\Support\Facades\DB;

class MoveAvatar
{
    /** @param 'up'|'down' $direction */
    public function handle(Avatar $avatar, string $direction): void
    {
        DB::transaction(function () use ($avatar, $direction): void {
            $orderedAvatars = Avatar::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();
            $targetIndex = $orderedAvatars->search(
                fn (Avatar $candidate): bool => $candidate->id === $avatar->id,
            );

            abort_if($targetIndex === false, 404);

            $neighborIndex = $direction === 'up' ? $targetIndex - 1 : $targetIndex + 1;
            $target = $orderedAvatars->get($targetIndex);
            $neighbor = $orderedAvatars->get($neighborIndex);

            abort_unless($target instanceof Avatar, 404);

            if ($neighbor instanceof Avatar) {
                $orderedAvatars[$targetIndex] = $neighbor;
                $orderedAvatars[$neighborIndex] = $target;
            }

            $orderedAvatars->each(function (Avatar $orderedAvatar, int $sortOrder): void {
                $orderedAvatar->update(['sort_order' => $sortOrder]);
            });
        });
    }
}
