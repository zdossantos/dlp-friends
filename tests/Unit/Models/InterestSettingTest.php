<?php

namespace Tests\Unit\Models;

use App\Models\InterestSetting;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

    public function test_current_returns_row_one_when_a_concurrent_creator_wins(): void
    {
        InterestSetting::query()->whereKey(1)->delete();
        $creatingEvent = 'eloquent.creating: '.InterestSetting::class;
        $concurrentCreationTriggered = false;

        Event::listen(
            $creatingEvent,
            function () use (&$concurrentCreationTriggered): void {
                if ($concurrentCreationTriggered) {
                    return;
                }

                $concurrentCreationTriggered = true;
                DB::table('interest_settings')->insert([
                    'id' => 1,
                    'max_selections' => 9,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            },
        );

        try {
            $setting = InterestSetting::current();
        } finally {
            Event::forget($creatingEvent);
        }

        expect($concurrentCreationTriggered)->toBeTrue()
            ->and($setting->id)->toBe(1)
            ->and($setting->max_selections)->toBe(9);
        $this->assertDatabaseCount('interest_settings', 1);
    }
}
