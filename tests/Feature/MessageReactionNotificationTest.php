<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\MessageLikedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MessageReactionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_liking_the_other_participants_message_sends_one_specific_in_app_and_web_push_notification(): void
    {
        [$author, $reactor, $conversation] = $this->conversation();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create();
        Notification::fake();

        $this->actingAs($reactor)->postJson(route('conversations.messages.like', [$conversation, $message]))->assertOk();
        $this->actingAs($reactor)->postJson(route('conversations.messages.like', [$conversation, $message]))->assertOk();

        Notification::assertSentToTimes($author, MessageLikedNotification::class, 1);
        Notification::assertSentTo($author, MessageLikedNotification::class, function (MessageLikedNotification $notification, array $channels) use ($author, $reactor, $conversation): bool {
            return $channels === ['database', 'broadcast', WebPushChannel::class]
                && $notification->toArray($author) === [
                    'category' => 'conversations',
                    'translation_key' => 'notifications.items.message_liked',
                    'parameters' => ['member' => $reactor->profile?->display_name],
                    'target_type' => 'conversation',
                    'target_id' => $conversation->id,
                ];
        });
    }

    public function test_liking_ones_own_message_is_forbidden_and_unliking_does_not_notify(): void
    {
        [$author, $reactor, $conversation] = $this->conversation();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create();
        Notification::fake();

        $this->actingAs($author)->postJson(route('conversations.messages.like', [$conversation, $message]))->assertForbidden();
        $this->actingAs($reactor)->deleteJson(route('conversations.messages.unlike', [$conversation, $message]))->assertOk();

        Notification::assertNothingSent();
    }

    public function test_push_copy_is_generic_and_access_is_rechecked(): void
    {
        [$author, $reactor, $conversation] = $this->conversation();
        $message = Message::factory()->for($conversation)->for($author, 'author')->create([
            'content' => 'Un contenu qui ne doit jamais apparaître',
        ]);
        $notification = new MessageLikedNotification($message, $reactor);

        expect($notification->webPushCopy($author, 'fr'))->toBe([
            'title' => 'Nouvelle réaction',
            'body' => 'Quelqu’un a aimé ton message.',
        ])->and($notification->webPushCopy($author, 'en'))->toBe([
            'title' => 'New reaction',
            'body' => 'Someone liked your message.',
        ])->and($notification->webPushAccessAllowed($author))->toBeTrue();
    }

    /** @return array{User, User, Conversation} */
    private function conversation(): array
    {
        $first = User::factory()->withProfile()->create();
        $second = User::factory()->withProfile()->create();
        [$low, $high] = $first->id < $second->id ? [$first, $second] : [$second, $first];
        $match = MemberMatch::factory()->create(['user_low_id' => $low->id, 'user_high_id' => $high->id]);

        return [$first, $second, $match->conversation()->create()];
    }
}
