<?php

namespace App\Actions;

use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class SetInterestStatus
{
    public function handle(Interest $interest, bool $active): void
    {
        DB::transaction(function () use ($interest, $active): void {
            $lockedInterest = Interest::query()
                ->whereKey($interest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedInterest->update(['is_active' => $active]);

            if (! $active) {
                DB::table('interest_profile')
                    ->where('interest_id', $lockedInterest->id)
                    ->where('is_selected', true)
                    ->update(['is_selected' => false]);

                return;
            }

            $limit = InterestSetting::query()
                ->whereKey(1)
                ->lockForUpdate()
                ->firstOrFail()
                ->max_selections;
            $profileIds = DB::table('interest_profile')
                ->where('interest_id', $lockedInterest->id)
                ->where('is_selected', false)
                ->orderBy('profile_id')
                ->pluck('profile_id');

            foreach ($profileIds as $profileId) {
                $lockedProfile = Profile::query()
                    ->whereKey((int) $profileId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $activeSelectionCount = DB::table('interest_profile')
                    ->join('interests', 'interests.id', '=', 'interest_profile.interest_id')
                    ->where('interest_profile.profile_id', $lockedProfile->id)
                    ->where('interest_profile.is_selected', true)
                    ->where('interests.is_active', true)
                    ->count();

                if ($activeSelectionCount >= $limit) {
                    continue;
                }

                DB::table('interest_profile')
                    ->where('profile_id', $lockedProfile->id)
                    ->where('interest_id', $lockedInterest->id)
                    ->update(['is_selected' => true]);
            }
        });

        $interest->is_active = $active;
    }
}
