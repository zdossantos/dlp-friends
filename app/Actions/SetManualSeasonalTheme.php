<?php

namespace App\Actions;

use App\Enums\SeasonalThemeName;
use App\Models\SeasonalTheme;
use Illuminate\Support\Facades\DB;

final class SetManualSeasonalTheme
{
    public function handle(?SeasonalThemeName $theme): void
    {
        DB::transaction(function () use ($theme): void {
            SeasonalTheme::query()->lockForUpdate()->get();
            SeasonalTheme::query()->update(['is_manually_active' => false]);

            if ($theme !== null) {
                SeasonalTheme::query()
                    ->where('theme', $theme)
                    ->update(['is_manually_active' => true]);
            }
        });
    }
}
