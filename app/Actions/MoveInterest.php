<?php

namespace App\Actions;

use App\Models\Interest;
use Illuminate\Support\Facades\DB;

class MoveInterest
{
    /** @param 'up'|'down' $direction */
    public function handle(Interest $interest, string $direction): void
    {
        DB::transaction(function () use ($interest, $direction): void {
            $lockedInterests = Interest::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $orderedInterests = $lockedInterests
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();
            $targetIndex = $orderedInterests->search(
                fn (Interest $candidate): bool => $candidate->id === $interest->id,
            );

            abort_if($targetIndex === false, 404);

            $neighborIndex = $direction === 'up'
                ? $targetIndex - 1
                : $targetIndex + 1;
            $target = $orderedInterests->get($targetIndex);
            $neighbor = $orderedInterests->get($neighborIndex);

            abort_unless($target instanceof Interest, 404);

            if ($neighbor instanceof Interest) {
                $orderedInterests[$targetIndex] = $neighbor;
                $orderedInterests[$neighborIndex] = $target;
            }

            $orderedInterests->each(function (Interest $orderedInterest, int $sortOrder): void {
                $orderedInterest->update(['sort_order' => $sortOrder]);
            });
        });
    }
}
