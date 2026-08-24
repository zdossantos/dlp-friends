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

    public function test_it_backfills_inactive_associations_and_remains_reversible_with_data(): void
    {
        $migration = require database_path('migrations/2026_08_24_000000_rename_passions_to_interests.php');
        $migration->down();

        try {
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
            $activePassionId = DB::table('passions')->insertGetId([
                'passion_category_id' => $categoryId,
                'name' => 'Attractions',
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inactivePassionId = DB::table('passions')->insertGetId([
                'passion_category_id' => $categoryId,
                'name' => 'Parades',
                'is_active' => false,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('passion_profile')->insert([
                ['profile_id' => $profileId, 'passion_id' => $activePassionId],
                ['profile_id' => $profileId, 'passion_id' => $inactivePassionId],
            ]);

            $migration->up();

            $this->assertTrue(Schema::hasColumns('interests', ['interest_category_id']));
            $this->assertTrue(Schema::hasColumns('interest_profile', ['interest_id', 'is_selected']));
            $this->assertDatabaseHas('interest_profile', [
                'profile_id' => $profileId,
                'interest_id' => $activePassionId,
                'is_selected' => true,
            ]);
            $this->assertDatabaseHas('interest_profile', [
                'profile_id' => $profileId,
                'interest_id' => $inactivePassionId,
                'is_selected' => false,
            ]);

            $migration->down();

            $this->assertTrue(Schema::hasColumns('passions', ['passion_category_id']));
            $this->assertTrue(Schema::hasColumn('passion_profile', 'passion_id'));
            $this->assertFalse(Schema::hasColumn('passion_profile', 'is_selected'));
            $this->assertDatabaseHas('passions', [
                'id' => $inactivePassionId,
                'passion_category_id' => $categoryId,
                'is_active' => false,
            ]);
            $this->assertDatabaseHas('passion_profile', [
                'profile_id' => $profileId,
                'passion_id' => $inactivePassionId,
            ]);
        } finally {
            if (Schema::hasTable('passion_profile')) {
                $migration->up();
            }
        }
    }

    public function test_renamed_foreign_keys_keep_their_cascade_behavior(): void
    {
        $profileId = DB::table('profiles')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'display_name' => 'Member',
            'visibility' => 'visible',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('interest_categories')->insertGetId([
            'name' => 'General',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $interestId = DB::table('interests')->insertGetId([
            'interest_category_id' => $categoryId,
            'name' => 'Attractions',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('interest_profile')->insert([
            'profile_id' => $profileId,
            'interest_id' => $interestId,
            'is_selected' => true,
        ]);

        DB::table('interest_categories')->where('id', $categoryId)->delete();

        $this->assertDatabaseMissing('interests', ['id' => $interestId]);
        $this->assertDatabaseMissing('interest_profile', [
            'profile_id' => $profileId,
            'interest_id' => $interestId,
        ]);
    }
}
