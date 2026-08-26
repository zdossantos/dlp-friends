<?php

namespace Tests\Feature;

use App\Models\MemberMatch;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConversationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversations_store_their_match_and_archived_state(): void
    {
        expect(Schema::hasTable('conversations'))->toBeTrue()
            ->and(Schema::hasColumns('conversations', [
                'id',
                'match_id',
                'archived_at',
                'created_at',
                'updated_at',
            ]))->toBeTrue();
    }

    public function test_a_match_cannot_have_more_than_one_conversation(): void
    {
        $match = MemberMatch::factory()->create();

        DB::table('conversations')->insert([
            'match_id' => $match->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('conversations')->insert([
            'match_id' => $match->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_migration_creates_conversations_for_existing_matches(): void
    {
        $migration = require database_path('migrations/2026_08_26_100000_create_conversations_table.php');
        $migration->down();
        $existingMatch = MemberMatch::factory()->create();

        $migration->up();

        $this->assertDatabaseHas('conversations', [
            'match_id' => $existingMatch->id,
            'archived_at' => null,
        ]);
    }
}
