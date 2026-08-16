<?php

namespace Tests\Feature\Database;

use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Enums\VisitFrequency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UsernameRemovalMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_migration_removes_username_and_can_restore_unique_legacy_values(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'username'));

        $first = User::query()->create([
            'email' => 'first@example.com',
            'birth_date' => '2000-01-01',
            'status' => UserStatus::Active,
            'password' => 'password',
        ]);
        $second = User::query()->create([
            'email' => 'second@example.com',
            'birth_date' => '2000-01-01',
            'status' => UserStatus::Active,
            'password' => 'password',
        ]);
        $third = User::query()->create([
            'email' => 'third@example.com',
            'birth_date' => '2000-01-01',
            'status' => UserStatus::Active,
            'password' => 'password',
        ]);

        foreach ([$first, $second, $third] as $user) {
            $user->profile()->create([
                'display_name' => $user->is($second)
                    ? "Same Name-{$third->id}"
                    : 'Same Name',
                'bio' => null,
                'visit_frequency' => VisitFrequency::Sometimes,
                'visibility' => ProfileVisibility::Visible,
                'onboarding_completed_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_08_16_020000_drop_username_from_users.php');
        $migration->down();

        $usernames = User::query()->orderBy('id')->pluck('username');
        $this->assertCount(3, $usernames);
        $this->assertNotNull($usernames[0]);
        $this->assertNotNull($usernames[1]);
        $this->assertNotSame($usernames[0], $usernames[1]);
        $this->assertCount(3, $usernames->unique());

        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'username'));
        $this->assertDatabaseCount('profiles', 3);
    }
}
