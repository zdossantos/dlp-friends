<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMatchNotification;
use App\Notifications\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_message_notification_targets_its_conversation_without_storing_the_body(): void
    {
        [$author, $recipient, $match] = $this->matchedMembers();
        $conversation = $match->conversation()->create();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create([
            'content' => 'Contenu strictement privé',
        ]);

        $payload = (new NewMessageNotification($message))->toArray($recipient);

        expect($payload)->toBe([
            'category' => 'conversations',
            'translation_key' => 'notifications.items.new_message',
            'parameters' => [
                'sender' => $author->profile?->display_name,
            ],
            'target_type' => 'conversation',
            'target_id' => $conversation->id,
        ])->and(json_encode($payload))->not->toContain('Contenu strictement privé');
    }

    public function test_a_new_match_notification_identifies_the_other_member_and_conversation(): void
    {
        [$recipient, $otherMember, $match] = $this->matchedMembers();
        $conversation = $match->conversation()->create();

        $payload = (new NewMatchNotification($match, $otherMember))->toArray($recipient);

        expect($payload)->toBe([
            'category' => 'conversations',
            'translation_key' => 'notifications.items.new_match',
            'parameters' => [
                'member' => $otherMember->profile?->display_name,
            ],
            'target_type' => 'conversation',
            'target_id' => $conversation->id,
        ]);
    }

    public function test_hidden_profiles_do_not_suppress_notifications_for_an_existing_unblocked_match(): void
    {
        [$author, $recipient, $match] = $this->matchedMembers();
        $author->profile()->update(['visibility' => ProfileVisibility::Hidden]);
        $conversation = $match->conversation()->create();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create();

        expect((new NewMessageNotification($message))->webPushAccessAllowed($recipient))->toBeTrue()
            ->and((new NewMatchNotification($match, $author))->webPushAccessAllowed($recipient))->toBeTrue();
    }

    public function test_a_block_suppresses_message_and_match_push_access(): void
    {
        [$author, $recipient, $match] = $this->matchedMembers();
        $conversation = $match->conversation()->create();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create();
        Block::factory()->create([
            'blocker_user_id' => $author->id,
            'blocked_user_id' => $recipient->id,
        ]);

        expect((new NewMessageNotification($message))->webPushAccessAllowed($recipient))->toBeFalse()
            ->and((new NewMatchNotification($match, $author))->webPushAccessAllowed($recipient))->toBeFalse();
    }

    /** @return array{User, User, MemberMatch} */
    private function matchedMembers(): array
    {
        $first = User::factory()->withProfile()->create();
        $second = User::factory()->withProfile()->create();
        [$lowUser, $highUser] = $first->id < $second->id
            ? [$first, $second]
            : [$second, $first];
        $match = MemberMatch::factory()->create([
            'user_low_id' => $lowUser->id,
            'user_high_id' => $highUser->id,
        ]);

        return [$lowUser, $highUser, $match];
    }
}
