<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SetManualSeasonalTheme;
use App\Http\Controllers\Controller;
use App\Models\SeasonalTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SeasonalThemeActivationController extends Controller
{
    public function store(SeasonalTheme $seasonalTheme, SetManualSeasonalTheme $setManualSeasonalTheme): RedirectResponse
    {
        Gate::authorize('update', $seasonalTheme);
        $setManualSeasonalTheme->handle($seasonalTheme->theme);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('administration.seasonal_themes.activated'),
        ]);

        return back();
    }

    public function destroy(SetManualSeasonalTheme $setManualSeasonalTheme): RedirectResponse
    {
        Gate::authorize('viewAny', SeasonalTheme::class);
        $setManualSeasonalTheme->handle(null);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('administration.seasonal_themes.deactivated'),
        ]);

        return back();
    }
}
