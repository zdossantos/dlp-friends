<?php

namespace Tests\Unit\Models;

use App\Models\InterestSetting;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InterestSettingTest extends TestCase
{
    use DatabaseMigrations;

    public function test_current_recreates_the_missing_primary_singleton_row(): void
    {
        InterestSetting::query()->whereKey(1)->delete();

        $setting = InterestSetting::current();

        expect($setting->id)->toBe(1)
            ->and($setting->max_selections)->toBe(5);
        $this->assertDatabaseCount('interest_settings', 1);
        $this->assertDatabaseHas('interest_settings', [
            'id' => 1,
            'max_selections' => 5,
        ]);
    }

    public function test_current_returns_row_one_when_another_creator_already_won(): void
    {
        InterestSetting::query()->whereKey(1)->delete();
        DB::table('interest_settings')->insert([
            'id' => 1,
            'max_selections' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $setting = InterestSetting::current();

        expect($setting->id)->toBe(1)
            ->and($setting->max_selections)->toBe(9);
        $this->assertDatabaseCount('interest_settings', 1);
    }

    public function test_current_does_not_open_a_consistent_read_before_its_idempotent_insert(): void
    {
        InterestSetting::query()->whereKey(1)->delete();
        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'interest_settings')) {
                $queries[] = ltrim($query->sql);
            }
        });

        InterestSetting::current();

        expect($queries)->not->toBeEmpty()
            ->and(strtolower(strtok($queries[0], ' ')))->toBe('insert');
    }
}
