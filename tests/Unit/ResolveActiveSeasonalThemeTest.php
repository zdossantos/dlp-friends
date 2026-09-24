<?php

namespace Tests\Unit;

use App\Actions\ResolveActiveSeasonalTheme;
use App\Enums\SeasonalThemeName;
use App\Models\SeasonalTheme;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveActiveSeasonalThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_activation_overrides_scheduled_themes(): void
    {
        $at = CarbonImmutable::parse('2026-12-20 12:00:00', config('app.timezone'));
        SeasonalTheme::query()->where('theme', 'halloween')->update(['is_manually_active' => true]);
        SeasonalTheme::query()->where('theme', 'christmas')->update([
            'starts_at' => $at->subDay(),
            'ends_at' => $at->addDay(),
        ]);

        $result = app(ResolveActiveSeasonalTheme::class)->handle($at);

        $this->assertSame(SeasonalThemeName::Halloween, $result->active);
        $this->assertNull($result->nextTransitionAt);
    }

    public function test_christmas_wins_scheduled_ties_independently_of_row_order(): void
    {
        $start = CarbonImmutable::parse('2026-12-20 00:00:00', config('app.timezone'));
        SeasonalTheme::query()->update(['starts_at' => $start, 'ends_at' => $start->addDays(10)]);

        $result = app(ResolveActiveSeasonalTheme::class)->handle($start->addDay());

        $this->assertSame(SeasonalThemeName::Christmas, $result->active);
    }

    public function test_returns_no_theme_when_no_period_contains_the_instant(): void
    {
        $at = CarbonImmutable::parse('2026-09-24 12:00:00', config('app.timezone'));

        $result = app(ResolveActiveSeasonalTheme::class)->handle($at);

        $this->assertNull($result->active);
        $this->assertNull($result->nextTransitionAt);
    }

    public function test_includes_the_schedule_start_and_excludes_its_end(): void
    {
        $start = CarbonImmutable::parse('2026-10-01 00:00:00', config('app.timezone'));
        $end = $start->addMonth();
        SeasonalTheme::query()->where('theme', 'halloween')->update([
            'starts_at' => $start,
            'ends_at' => $end,
        ]);

        $this->assertSame(
            SeasonalThemeName::Halloween,
            app(ResolveActiveSeasonalTheme::class)->handle($start)->active,
        );
        $this->assertNull(app(ResolveActiveSeasonalTheme::class)->handle($end)->active);
    }

    public function test_selects_the_schedule_with_the_newest_start_during_overlap(): void
    {
        $at = CarbonImmutable::parse('2026-12-20 12:00:00', config('app.timezone'));
        SeasonalTheme::query()->where('theme', 'christmas')->update([
            'starts_at' => $at->subDays(10),
            'ends_at' => $at->addDays(10),
        ]);
        SeasonalTheme::query()->where('theme', 'halloween')->update([
            'starts_at' => $at->subDay(),
            'ends_at' => $at->addDay(),
        ]);

        $this->assertSame(
            SeasonalThemeName::Halloween,
            app(ResolveActiveSeasonalTheme::class)->handle($at)->active,
        );
    }

    public function test_uses_enum_priority_if_corrupted_data_contains_two_manual_themes(): void
    {
        SeasonalTheme::query()->update(['is_manually_active' => true]);

        $result = app(ResolveActiveSeasonalTheme::class)->handle(
            CarbonImmutable::parse('2026-09-24 12:00:00', config('app.timezone')),
        );

        $this->assertSame(SeasonalThemeName::Christmas, $result->active);
        $this->assertNull($result->nextTransitionAt);
    }

    public function test_returns_the_nearest_future_start_or_end_as_the_next_transition(): void
    {
        $at = CarbonImmutable::parse('2026-10-15 12:00:00', config('app.timezone'));
        SeasonalTheme::query()->where('theme', 'halloween')->update([
            'starts_at' => $at->subDay(),
            'ends_at' => $at->addDays(2),
        ]);
        SeasonalTheme::query()->where('theme', 'christmas')->update([
            'starts_at' => $at->addDay(),
            'ends_at' => $at->addMonth(),
        ]);

        $result = app(ResolveActiveSeasonalTheme::class)->handle($at);

        $this->assertSame(SeasonalThemeName::Halloween, $result->active);
        $this->assertTrue($at->addDay()->equalTo($result->nextTransitionAt));
    }
}
