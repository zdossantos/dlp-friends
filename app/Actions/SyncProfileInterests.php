<?php

namespace App\Actions;

use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncProfileInterests
{
    /** @param list<int> $interestIds */
    public function handle(Profile $profile, array $interestIds): void
    {
        DB::transaction(function () use ($profile, $interestIds): void {
            $submittedIds = collect($interestIds)->sort()->values();
            $lockedInterests = Interest::query()
                ->whereKey($submittedIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'is_active']);

            if (
                $lockedInterests->pluck('id')->all() !== $submittedIds->all()
                || $lockedInterests->contains(
                    fn (Interest $interest): bool => ! $interest->is_active,
                )
            ) {
                throw ValidationException::withMessages([
                    'interest_ids' => 'Un ou plusieurs intérêts ne sont plus disponibles.',
                ]);
            }

            InterestSetting::current();
            $setting = InterestSetting::query()
                ->whereKey(1)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedProfile = Profile::query()
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->firstOrFail();
            $currentIds = $lockedProfile->interests()
                ->pluck('interests.id');

            if (
                $submittedIds->count() > $setting->max_selections
                && $submittedIds->diff($currentIds)->isNotEmpty()
            ) {
                throw ValidationException::withMessages([
                    'interest_ids' => "Vous pouvez sélectionner au maximum {$setting->max_selections} intérêts.",
                ]);
            }

            DB::table('interest_profile')
                ->where('profile_id', $lockedProfile->id)
                ->where('is_selected', true)
                ->whereIn(
                    'interest_id',
                    Interest::query()
                        ->select('id')
                        ->where('is_active', true),
                )
                ->when(
                    $submittedIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('interest_id', $submittedIds->all()),
                )
                ->delete();

            foreach ($submittedIds as $interestId) {
                DB::table('interest_profile')->updateOrInsert(
                    [
                        'profile_id' => $lockedProfile->id,
                        'interest_id' => $interestId,
                    ],
                    ['is_selected' => true],
                );
            }
        });

        $profile->unsetRelation('interests');
        $profile->unsetRelation('interestHistory');
    }
}
