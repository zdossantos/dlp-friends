<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InterestRenameMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renames_the_catalog_and_preserves_existing_associations(): void
    {
        $migration = require database_path('migrations/2026_08_24_000000_rename_passions_to_interests.php');
        $migration->down();

        $profileId = DB::table('profiles')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'display_name' => 'Member',
            'visibility' => 'visible',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('passion_categories')->insertGetId([
            'name' => 'General',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $passionId = DB::table('passions')->insertGetId([
            'passion_category_id' => $categoryId,
            'name' => 'Attractions',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('passion_profile')->insert([
            'profile_id' => $profileId,
            'passion_id' => $passionId,
        ]);

        $migration->up();

        $this->assertTrue(Schema::hasColumns('interests', ['interest_category_id']));
        $this->assertTrue(Schema::hasColumns('interest_profile', ['interest_id', 'is_selected']));
        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $profileId,
            'interest_id' => $passionId,
            'is_selected' => true,
        ]);
    }
}
