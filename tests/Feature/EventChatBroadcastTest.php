<?php

namespace Tests\Feature;

use App\Actions\SendEventChatMessage;
use App\Events\EventChatMessageSent;
use App\Models\Event;
use App\Models\EventChat;
use App\Models\EventChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class EventChatBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_dispatches_one_event_with_a_minimal_private_contract(): void
    {
        [$chat, $author] = $this->eventChat();
        EventFacade::fake([EventChatMessageSent::class]);

        $message = app(SendEventChatMessage::class)->handle($author, $chat, '<b>texte brut</b>');

        EventFacade::assertDispatchedTimes(EventChatMessageSent::class, 1);
        EventFacade::assertDispatched(EventChatMessageSent::class, function ($event) use ($message): bool {
            $channels = $event->broadcastOn();

            return $event instanceof ShouldBroadcast
                && $event instanceof ShouldDispatchAfterCommit
                && count($channels) === 1
                && $channels[0] instanceof PrivateChannel
                && $channels[0]->name === "private-event-chat.{$message->event_chat_id}"
                && $event->broadcastAs() === 'event-chat.message.sent'
                && $event->broadcastWith()['content'] === '<b>texte brut</b>';
        });
    }

    public function test_only_members_can_authorize_the_private_channel(): void
    {
        [$chat, $organizer] = $this->eventChat();
        $outsider = User::factory()->withProfile()->create();
        $this->usePusherBroadcaster();
        $credentials = [
            'socket_id' => '1234.5678',
            'channel_name' => "private-event-chat.{$chat->id}",
        ];

        $this->actingAs($organizer)->postJson('/broadcasting/auth', $credentials)->assertOk();
        $this->actingAs($outsider)->postJson('/broadcasting/auth', $credentials)->assertForbidden();
    }

    /** @return array{EventChat, User} */
    private function eventChat(): array
    {
        $organizer = User::factory()->withProfile()->create();
        $event = Event::factory()->create(['organizer_user_id' => $organizer->id]);

        return [EventChat::factory()->for($event)->create(), $organizer];
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
}
