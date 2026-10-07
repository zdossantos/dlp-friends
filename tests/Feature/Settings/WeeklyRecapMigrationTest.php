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
