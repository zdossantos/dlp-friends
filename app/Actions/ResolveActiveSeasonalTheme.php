<?php

namespace App\Actions;

use App\Data\ActiveSeasonalThemeData;
use App\Models\SeasonalTheme;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class ResolveActiveSeasonalTheme
{
    public function handle(?CarbonImmutable $at = null): ActiveSeasonalThemeData
    {
        $at ??= CarbonImmutable::now(config('app.timezone'));
        $themes = SeasonalTheme::query()->get();
        $manual = $this->manualTheme($themes);
        $scheduled = $themes
            ->filter(fn (SeasonalTheme $theme): bool => $theme->starts_at !== null
                && $theme->ends_at !== null
                && $theme->starts_at->lte($at)
                && $theme->ends_at->gt($at))
            ->sort(function (SeasonalTheme $left, SeasonalTheme $right): int {
                $startComparison = $right->starts_at <=> $left->starts_at;

                return $startComparison !== 0
                    ? $startComparison
                    : $right->theme->schedulePriority() <=> $left->theme->schedulePriority();
            })
            ->first();
        $active = $manual === null ? $scheduled?->theme : $manual->theme;

        return new ActiveSeasonalThemeData(
            $active,
            $manual === null ? $this->nextTransition($themes, $at) : null,
        );
    }

    /** @param Collection<int, SeasonalTheme> $themes */
    private function manualTheme(Collection $themes): ?SeasonalTheme
    {
        return $themes
            ->where('is_manually_active', true)
            ->sortByDesc(fn (SeasonalTheme $theme): int => $theme->theme->schedulePriority())
            ->first();
    }

    /** @param Collection<int, SeasonalTheme> $themes */
    private function nextTransition(Collection $themes, CarbonImmutable $at): ?CarbonImmutable
    {
        /** @var CarbonImmutable|null */
        return $themes
            ->flatMap(fn (SeasonalTheme $theme): array => [$theme->starts_at, $theme->ends_at])
            ->filter(fn (?CarbonImmutable $boundary): bool => $boundary?->gt($at) === true)
            ->sort()
            ->first();
    }
}
