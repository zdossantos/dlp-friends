<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(DatabaseMigrations::class);

test('the migration enables weekly email for existing accounts', function () {
    $migration = require database_path('migrations/2026_10_07_150000_add_weekly_recap_preferences_to_users.php');
    $migration->down();
    $member = User::factory()->make();
    $attributes = $member->getAttributes();
    unset($attributes['weekly_recap_messages'], $attributes['weekly_recap_matches']);
    $id = DB::table('users')->insertGetId($attributes);
    try {
        $migration->up();
        expect(User::query()->findOrFail($id)->weekly_recap_messages)->toBeTrue()
            ->and(User::query()->findOrFail($id)->weekly_recap_matches)->toBeTrue();
    } finally {
        if (! Schema::hasColumn('users', 'weekly_recap_messages')) {
            $migration->up();
        }
    }
});

test('weekly recap opt in migration disables existing preferences and database defaults', function () {
    $migration = require database_path('migrations/2026_10_17_100000_disable_weekly_email_recaps_by_default.php');
    $migration->down();
    $member = User::factory()->create(['weekly_recap_messages' => true, 'weekly_recap_matches' => true]);
    $disabled = User::factory()->create(['weekly_recap_messages' => false, 'weekly_recap_matches' => false]);

    $migration->up();

    expect($member->fresh()->weekly_recap_messages)->toBeFalse()
        ->and($member->fresh()->weekly_recap_matches)->toBeFalse()
        ->and($disabled->fresh()->weekly_recap_messages)->toBeFalse()
        ->and($disabled->fresh()->weekly_recap_matches)->toBeFalse();

    $attributes = User::factory()->make()->getAttributes();
    unset($attributes['weekly_recap_messages'], $attributes['weekly_recap_matches']);
    $id = DB::table('users')->insertGetId($attributes);

    expect(User::query()->findOrFail($id)->weekly_recap_messages)->toBeFalse()
        ->and(User::query()->findOrFail($id)->weekly_recap_matches)->toBeFalse();
});
