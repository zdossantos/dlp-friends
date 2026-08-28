<?php

namespace Tests\Feature;

use App\Actions\BlockUser;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BlockUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_block_is_idempotent_and_archives_the_pair_conversation(): void
    {
        [$blocker, $blocked, $conversation] = $this->conversationMembers();

        $first = app(BlockUser::class)->handle($blocker, $blocked);
        $second = app(BlockUser::class)->handle($blocker, $blocked);

        expect($second->is($first))->toBeTrue()
            ->and(Block::query()->count())->toBe(1)
            ->and($conversation->fresh()->archived_at)->not->toBeNull();
    }

    public function test_a_block_is_bilateral_for_relationship_checks(): void
    {
        $left = User::factory()->create();
        $right = User::factory()->create();
        Block::factory()->create([
            'blocker_user_id' => $left->id,
            'blocked_user_id' => $right->id,
        ]);

        expect($left->hasBlockedRelationshipWith($right))->toBeTrue()
            ->and($right->hasBlockedRelationshipWith($left))->toBeTrue();
    }

    public function test_blocking_succeeds_without_a_match(): void
    {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create();

        $block = app(BlockUser::class)->handle($blocker, $blocked);

        expect($block->blocker_user_id)->toBe($blocker->id)
            ->and($block->blocked_user_id)->toBe($blocked->id);
    }

    public function test_an_existing_archive_timestamp_is_preserved(): void
    {
        [$blocker, $blocked, $conversation] = $this->conversationMembers();
        $archivedAt = now()->subDay()->startOfSecond();
        $conversation->update(['archived_at' => $archivedAt]);

        app(BlockUser::class)->handle($blocker, $blocked);

        expect($conversation->fresh()->archived_at?->equalTo($archivedAt))->toBeTrue();
    }

    public function test_a_member_cannot_block_themselves(): void
    {
        $member = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(BlockUser::class)->handle($member, $member);
    }

    /** @return array{User, User, Conversation} */
    private function conversationMembers(): array
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        [$lowUser, $highUser] = $first->id < $second->id
            ? [$first, $second]
            : [$second, $first];
        $match = MemberMatch::factory()->create([
            'user_low_id' => $lowUser->id,
            'user_high_id' => $highUser->id,
        ]);

        return [$lowUser, $highUser, $match->conversation()->create()];
    }
}
