<?php

namespace Tests\Feature;

use App\Models\SeasonalTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeasonalThemeSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_creates_the_two_supported_seasonal_themes(): void
    {
        $this->assertSame(
            ['christmas', 'halloween'],
            DB::table('seasonal_themes')->pluck('theme')->sort()->values()->all(),
        );

        $this->assertCount(2, SeasonalTheme::query()->get());
    }
}
