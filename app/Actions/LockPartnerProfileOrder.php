<?php

namespace App\Actions;

use App\Models\PartnerProfile;
use App\Models\PartnerSetting;
use Illuminate\Database\Eloquent\Collection;

final class LockPartnerProfileOrder
{
    /** @return Collection<int, PartnerProfile> */
    public function handle(): Collection
    {
        PartnerSetting::query()
            ->whereKey(1)
            ->lockForUpdate()
            ->firstOrFail();

        return PartnerProfile::query()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
