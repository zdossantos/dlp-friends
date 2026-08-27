<?php

namespace Tests\Feature;

use App\Actions\SendMessage;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class MessageBroadcastTest extends TestCase
{
    use DatabaseMigrations;

    public function test_sending_dispatches_a_message_event_after_persistence(): void
    {
        [$author, , $conversation] = $this->conversationMembers();
        Event::fake([MessageSent::class]);

        $message = app(SendMessage::class)->handle($author, $conversation, 'Bonjour !');

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
        Event::assertDispatched(
            MessageSent::class,
            fn (MessageSent $event): bool => $event->message->is($message),
        );
    }

    public function test_the_event_exposes_a_minimal_private_broadcast_contract(): void
    {
        [$author, , $conversation] = $this->conversationMembers();
        $message = Message::factory()
            ->for($conversation)
            ->for($author, 'author')
            ->create(['content' => '<b>texte brut</b>']);
        $event = new MessageSent($message);
        $channel = $event->broadcastOn();

        expect($event)->toBeInstanceOf(ShouldBroadcast::class)
            ->and($event)->toBeInstanceOf(ShouldDispatchAfterCommit::class)
            ->and($channel)->toBeInstanceOf(PrivateChannel::class)
            ->and($channel->name)->toBe("private-conversation.{$conversation->id}")
            ->and($event->broadcastAs())->toBe('message.sent')
            ->and($event->broadcastWith())->toBe([
                'id' => $message->id,
                'conversation_id' => $conversation->id,
                'author_user_id' => $author->id,
                'content' => '<b>texte brut</b>',
                'created_at' => $message->created_at?->toISOString(),
                'updated_at' => $message->updated_at?->toISOString(),
            ]);
    }

    public function test_a_rolled_back_transaction_dispatches_no_message_event(): void
    {
        [$author, , $conversation] = $this->conversationMembers();
        Event::fake([MessageSent::class]);

        try {
            DB::transaction(function () use ($author, $conversation): void {
                app(SendMessage::class)->handle($author, $conversation, 'Annulé');

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toBe('rollback');
        }

        $this->assertDatabaseCount('messages', 0);
        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_the_message_exists_before_the_broadcast_job_executes(): void
    {
        [$author, , $conversation] = $this->conversationMembers();
        Queue::fake();

        $message = app(SendMessage::class)->handle($author, $conversation, 'Persisté');

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
        Queue::assertPushed(BroadcastEvent::class);
    }

    public function test_only_members_of_an_active_conversation_can_authorize_the_channel(): void
    {
        [$lowUser, $highUser, $conversation] = $this->conversationMembers();
        $outsider = User::factory()->withProfile()->create();
        $admin = User::factory()->withProfile()->admin()->create();
        $this->usePusherBroadcaster();

        foreach ([$lowUser, $highUser] as $member) {
            $this->actingAs($member)
                ->postJson('/broadcasting/auth', $this->channelCredentials($conversation))
                ->assertOk();
        }

        foreach ([$outsider, $admin] as $nonMember) {
            $this->actingAs($nonMember)
                ->postJson('/broadcasting/auth', $this->channelCredentials($conversation))
                ->assertForbidden();
        }

        $conversation->update(['archived_at' => now()]);

        foreach ([$lowUser, $highUser] as $member) {
            $this->actingAs($member)
                ->postJson('/broadcasting/auth', $this->channelCredentials($conversation))
                ->assertForbidden();
        }
    }

    /** @return array{User, User, Conversation} */
    private function conversationMembers(): array
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

        return [$lowUser, $highUser, $match->conversation()->create()];
    }

    private function usePusherBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app',
        ]);
        Broadcast::purge();
        Broadcast::setDefaultDriver('pusher');

        require base_path('routes/channels.php');
    }

    /** @return array{socket_id: string, channel_name: string} */
    private function channelCredentials(Conversation $conversation): array
    {
        return [
            'socket_id' => '1234.5678',
            'channel_name' => "private-conversation.{$conversation->id}",
        ];
    }
}
