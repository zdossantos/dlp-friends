<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ResolveActiveSeasonalTheme;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSeasonalThemeRequest;
use App\Models\SeasonalTheme;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SeasonalThemeController extends Controller
{
    public function index(ResolveActiveSeasonalTheme $resolveActiveSeasonalTheme): Response
    {
        Gate::authorize('viewAny', SeasonalTheme::class);
        $resolved = $resolveActiveSeasonalTheme->handle();

        return Inertia::render('Admin/SeasonalThemes/Index', [
            'themes' => SeasonalTheme::query()->orderBy('id')->get()->map(
                fn (SeasonalTheme $theme): array => [
                    'theme' => $theme->theme->value,
                    'is_manually_active' => $theme->is_manually_active,
                    'starts_at' => $theme->starts_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
                    'ends_at' => $theme->ends_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
                ],
            ),
            'activeTheme' => $resolved->active?->value,
            'nextTransitionAt' => $resolved->nextTransitionAt?->toISOString(),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function update(UpdateSeasonalThemeRequest $request, SeasonalTheme $seasonalTheme): RedirectResponse
    {
        $validated = $request->validated();
        $seasonalTheme->update([
            'starts_at' => $this->parseLocalDate($validated['starts_at']),
            'ends_at' => $this->parseLocalDate($validated['ends_at']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('administration.seasonal_themes.schedule_saved'),
        ]);

        return back();
    }

    private function parseLocalDate(?string $value): ?CarbonImmutable
    {
        return $value === null
            ? null
            : CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, config('app.timezone'))->utc();
    }
}
