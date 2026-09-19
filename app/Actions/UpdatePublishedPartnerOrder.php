<?php

namespace App\Actions;

use App\Models\PartnerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdatePublishedPartnerOrder
{
    /** @param list<int> $orderedIds */
    public function handle(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            $published = PartnerProfile::query()
                ->published()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

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
