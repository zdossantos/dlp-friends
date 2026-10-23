<?php

namespace Tests\Feature;

use App\Enums\EventRegistrationStatus;
use App\Events\GroupChatMessageReactionUpdated;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\User;
use App\Notifications\GroupChatMessageLikedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EventChatMessageReactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_event_member_can_like_and_unlike_another_members_message(): void
    {
        [$event, $chat, $author, $reactor] = $this->scenario();
        $message = GroupChatMessage::factory()->for($chat)->for($author, 'author')->create();
        EventFacade::fake([GroupChatMessageReactionUpdated::class]);
        Notification::fake();

        $this->actingAs($reactor)
            ->postJson(route('events.chat.messages.like', [$event, $message]))
            ->assertOk()
            ->assertJsonPath('data.reaction_count', 1)
            ->assertJsonPath('data.reacted', true);

        Notification::assertSentTo(
            $author,
            GroupChatMessageLikedNotification::class,
            fn (GroupChatMessageLikedNotification $notification, array $channels): bool => $notification->messageId === $message->id
                && $notification->reactorId === $reactor->id
                && $notification->toArray($author)['target_id'] === $event->id,
        );

        $this->actingAs($reactor)
            ->deleteJson(route('events.chat.messages.unlike', [$event, $message]))
            ->assertOk()
            ->assertJsonPath('data.reaction_count', 0)
            ->assertJsonPath('data.reacted', false);

        $this->assertDatabaseEmpty('group_chat_message_reactions');
    }

    public function test_a_member_cannot_like_their_own_event_chat_message(): void
    {
        [$event, $chat, $author] = $this->scenario();
        $message = GroupChatMessage::factory()->for($chat)->for($author, 'author')->create();

        $this->actingAs($author)
            ->postJson(route('events.chat.messages.like', [$event, $message]))
            ->assertForbidden();

        $this->assertDatabaseEmpty('group_chat_message_reactions');
    }

    public function test_event_chat_history_exposes_reactions_for_the_current_member(): void
    {
        [$event, $chat, $author, $reactor] = $this->scenario();
        $message = GroupChatMessage::factory()->for($chat)->for($author, 'author')->create();
        $message->reactions()->create(['user_id' => $reactor->id]);

        $this->actingAs($reactor)
            ->get(route('events.chat.show', $event))
            ->assertInertia(fn ($page) => $page
                ->where('messages.data.0.reaction_count', 1)
                ->where('messages.data.0.reacted_by_current_user', true));
    }

    /** @return array{Event, GroupChat, User, User} */
    private function scenario(): array
    {
        $organizer = User::factory()->withProfile()->create();
        $participant = User::factory()->withProfile()->create();
        $event = Event::factory()->create(['organizer_user_id' => $organizer->id]);
        $chat = GroupChat::factory()->for($event)->create();
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => EventRegistrationStatus::Accepted,
        ]);

        return [$event, $chat, $organizer, $participant];
    }
}
