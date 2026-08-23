<?php

namespace Tests\Feature;

use App\Models\MemberMatch;
use App\Models\Passion;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscoverySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_profile_cannot_attach_the_same_passion_twice(): void
    {
        $profile = User::factory()->withProfile()->create()->profile;
        $passion = Passion::factory()->create();

        $profile->passions()->attach($passion);

        $this->expectException(QueryException::class);

        $profile->passions()->attach($passion);
    }

    public function test_a_swipe_rejects_a_decision_outside_the_storage_enum(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('swipes')->insert([
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'decision' => 'super-like',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_member_can_only_swipe_once_per_target(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        Swipe::factory()->create([
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
        ]);

        $this->expectException(QueryException::class);

        Swipe::factory()->create([
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
        ]);
    }

    public function test_a_member_pair_can_only_have_one_canonical_match(): void
    {
        $low = User::factory()->create();
        $high = User::factory()->create();

        MemberMatch::factory()->create([
            'user_low_id' => min($low->id, $high->id),
            'user_high_id' => max($low->id, $high->id),
        ]);

        $this->expectException(QueryException::class);

        MemberMatch::factory()->create([
            'user_low_id' => min($low->id, $high->id),
            'user_high_id' => max($low->id, $high->id),
        ]);
    }
}
