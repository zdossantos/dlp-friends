<?php

namespace Tests\Feature;

use App\Models\SeasonalTheme;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeasonalThemeInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_shares_the_active_theme_and_next_transition(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $at = CarbonImmutable::parse('2026-10-30 12:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($at);
        SeasonalTheme::query()->where('theme', 'halloween')->update([
            'starts_at' => $at->subDay(),
            'ends_at' => $at->addDay(),
        ]);
        SeasonalTheme::query()->where('theme', 'christmas')->update([
            'starts_at' => $at->addHours(6),
            'ends_at' => $at->addMonth(),
        ]);
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seasonalTheme.active', 'halloween')
                ->where('seasonalTheme.nextTransitionAt', $at->addHours(6)->toIso8601String()));
    }
}
