<?php

namespace Tests\Feature\Database;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_legacy_usernames_and_assigns_the_default_role(): void
    {
        $discoveryMigration = require database_path('migrations/2026_08_23_000000_create_discovery_tables.php');
        $discoveryMigration->down();
        $removalMigration = require database_path('migrations/2026_08_16_020000_drop_username_from_users.php');
        $removalMigration->down();
        $migration = require database_path('migrations/2026_08_16_010000_create_profiles_and_roles.php');
        $migration->down();

        $now = now();
        $firstId = DB::table('users')->insertGetId([
            'username' => 'Magic Friend',
            'email' => 'magic@example.com',
            'email_verified_at' => $now,
            'birth_date' => '2000-01-01',
            'status' => UserStatus::Active->value,
            'password' => Hash::make('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $secondId = DB::table('users')->insertGetId([
            'username' => 'Park Pal',
            'email' => 'park@example.com',
            'email_verified_at' => $now,
            'birth_date' => '1995-06-15',
            'status' => UserStatus::Active->value,
            'password' => Hash::make('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->assertDatabaseCount('users', 2);

        $migration->up();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('profiles', 2);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $firstId,
            'display_name' => 'Magic Friend',
            'visit_frequency' => null,
            'onboarding_completed_at' => null,
        ]);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $secondId,
            'display_name' => 'Park Pal',
        ]);
        $this->assertTrue(Schema::hasColumn('users', 'username'));
        $this->assertDatabaseCount('user_roles', 2);
        $this->assertDatabaseHas('roles', ['name' => RoleName::Admin->value]);
        $this->assertDatabaseHas('roles', ['name' => RoleName::User->value]);

        $removalMigration->up();
        $discoveryMigration->up();
    }

    public function test_profile_migration_rollback_avoids_generated_username_collisions(): void
    {
        $discoveryMigration = require database_path('migrations/2026_08_23_000000_create_discovery_tables.php');
        $discoveryMigration->down();
        $removalMigration = require database_path('migrations/2026_08_16_020000_drop_username_from_users.php');
        $removalMigration->down();
        $migration = require database_path('migrations/2026_08_16_010000_create_profiles_and_roles.php');
        $migration->down();
        $migration->up();

        $now = now();
        $userIds = collect(['first', 'second', 'third'])->map(fn (string $label): int => DB::table('users')->insertGetId([
            'username' => null,
            'email' => "{$label}@example.com",
            'email_verified_at' => $now,
            'birth_date' => '2000-01-01',
            'status' => UserStatus::Active->value,
            'password' => Hash::make('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        foreach ($userIds as $index => $userId) {
            DB::table('profiles')->insert([
                'user_id' => $userId,
                'display_name' => $index === 1 ? "Same Name-{$userIds[2]}" : 'Same Name',
                'visibility' => 'visible',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $migration->down();

        $usernames = DB::table('users')->orderBy('id')->pluck('username');
        $this->assertCount(3, $usernames->unique());

        $migration->up();
        $removalMigration->up();
        $discoveryMigration->up();
    }
}
