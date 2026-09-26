<?php

namespace Tests\Feature\Admin;

use App\Models\SeasonalTheme;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageSeasonalThemesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_programs_a_theme_in_the_application_timezone(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.seasonal-themes.update', 'halloween'), [
            'starts_at' => '2026-10-20T18:00',
            'ends_at' => '2026-11-02T08:00',
        ])->assertRedirect()
            ->assertInertiaFlash('toast', [
                'type' => 'success',
                'message' => 'La programmation du thème a été enregistrée.',
            ]);

        $theme = SeasonalTheme::query()->where('theme', 'halloween')->firstOrFail();
        $this->assertSame(
            '2026-10-20T18:00',
            $theme->starts_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
        );
        $this->assertSame(
            '2026-11-02T08:00',
            $theme->ends_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
        );
    }

    public function test_activating_one_theme_deactivates_the_other(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        SeasonalTheme::query()->where('theme', 'halloween')->update(['is_manually_active' => true]);

        $this->actingAs($admin)->post(route('admin.seasonal-themes.activate', 'christmas'))
            ->assertRedirect()
            ->assertInertiaFlash('toast', [
                'type' => 'success',
                'message' => 'Le thème saisonnier a été activé manuellement.',
            ]);

        $this->assertFalse((bool) SeasonalTheme::query()->where('theme', 'halloween')->value('is_manually_active'));
        $this->assertTrue((bool) SeasonalTheme::query()->where('theme', 'christmas')->value('is_manually_active'));
    }

    public function test_guest_is_redirected_and_member_is_forbidden(): void
    {
        $this->patch(route('admin.seasonal-themes.update', 'halloween'), [
            'starts_at' => null,
            'ends_at' => null,
        ])->assertRedirect(route('login'));

        $member = User::factory()->withProfile()->create();
        $this->actingAs($member)->patch(route('admin.seasonal-themes.update', 'halloween'), [
            'starts_at' => null,
            'ends_at' => null,
        ])->assertForbidden();
    }

    public function test_unknown_theme_returns_not_found(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.seasonal-themes.update', 'spring'), [
            'starts_at' => null,
            'ends_at' => null,
        ])->assertNotFound();
    }

    public function test_schedule_requires_both_boundaries(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.seasonal-themes.update', 'halloween'), [
            'starts_at' => '2026-10-20T18:00',
            'ends_at' => null,
        ])->assertSessionHasErrors('ends_at');

        $this->actingAs($admin)->patch(route('admin.seasonal-themes.update', 'halloween'), [
            'starts_at' => null,
            'ends_at' => '2026-11-02T08:00',
        ])->assertSessionHasErrors('starts_at');
    }

    public function test_schedule_end_must_be_after_start(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.seasonal-themes.update', 'halloween'), [
            'starts_at' => '2026-11-02T08:00',
            'ends_at' => '2026-10-20T18:00',
        ])->assertSessionHasErrors('ends_at');
    }

    public function test_invalid_schedule_does_not_change_persisted_dates(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        $theme = SeasonalTheme::query()->where('theme', 'halloween')->firstOrFail();
        $theme->update([
            'starts_at' => CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-11-01 12:00:00', 'UTC'),
        ]);

        $this->actingAs($admin)->patch(route('admin.seasonal-themes.update', 'halloween'), [
            'starts_at' => 'invalid',
            'ends_at' => '2026-11-02T08:00',
        ])->assertSessionHasErrors('starts_at');

        $theme->refresh();
        $this->assertTrue(CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC')->equalTo($theme->starts_at));
        $this->assertTrue(CarbonImmutable::parse('2026-11-01 12:00:00', 'UTC')->equalTo($theme->ends_at));
    }

    public function test_admin_can_disable_the_manual_theme(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        SeasonalTheme::query()->where('theme', 'halloween')->update(['is_manually_active' => true]);

        $this->actingAs($admin)->post(route('admin.seasonal-themes.deactivate'))
            ->assertRedirect()
            ->assertInertiaFlash('toast', [
                'type' => 'success',
                'message' => 'Le forçage manuel du thème a été désactivé.',
            ]);

        $this->assertFalse(SeasonalTheme::query()->where('is_manually_active', true)->exists());
    }
}
