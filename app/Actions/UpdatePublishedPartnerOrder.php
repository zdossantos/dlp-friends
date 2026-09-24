<?php

namespace App\Actions;

use App\Models\PartnerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdatePublishedPartnerOrder
{
    public function __construct(private readonly LockPartnerProfileOrder $lockPartnerProfileOrder) {}

    /** @param list<int> $orderedIds */
    public function handle(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            $published = $this->lockPartnerProfileOrder
                ->handle()
                ->filter(fn (PartnerProfile $profile): bool => $profile->is_published
                    && $profile->published_revision_id !== null);

            $expectedIds = $published->pluck('id')->sort()->values()->all();
            $submittedIds = collect($orderedIds)->sort()->values()->all();

            if ($submittedIds !== $expectedIds || count($orderedIds) !== count(array_unique($orderedIds))) {
                throw ValidationException::withMessages([
                    'ordered_ids' => __('administration.partners.errors.order_exact'),
                ]);
            }

            $publishedById = $published->keyBy('id');

            foreach ($orderedIds as $index => $id) {
                $publishedById->get($id)?->update(['position' => $index + 1]);
            }
        });
    }
}
