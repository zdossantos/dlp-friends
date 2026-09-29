<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Event;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_filter_their_notifications_by_category_and_unread_state(): void
    {
        [$member, , $conversation] = $this->conversationMembers();
        $unreadEvent = $this->notification($member, 'events', $conversation);
        $this->notification($member, 'conversations', $conversation);
        $readEvent = $this->notification($member, 'events', $conversation);
        $readEvent->markAsRead();

        $this->actingAs($member)
            ->get(route('notifications.index', ['category' => 'events', 'unread' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->where('filters.category', 'events')
                ->where('filters.unread', true)
                ->has('notifications.data', 1)
                ->where('notifications.data.0.id', $unreadEvent->id));
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $member = User::factory()->withProfile()->create();

        $this->actingAs($member)
            ->get(route('notifications.index', ['category' => 'unknown']))
            ->assertSessionHasErrors('category');
    }

    public function test_reading_a_notification_marks_only_the_owners_item_and_opens_its_conversation(): void
    {
        [$member, , $conversation] = $this->conversationMembers();
        $other = User::factory()->withProfile()->create();
        $notification = $this->notification($member, 'conversations', $conversation);

        $this->actingAs($other)
            ->patch(route('notifications.read', $notification))
            ->assertNotFound();
        expect($notification->fresh()?->read_at)->toBeNull();

        $this->actingAs($member)
            ->withHeader('X-Inertia', 'true')
            ->patch(route('notifications.read', $notification))
            ->assertRedirect(route('conversations.show', $conversation, absolute: false))
            ->assertHeaderMissing('X-Inertia-Location');
        expect($notification->fresh()?->read_at)->not->toBeNull();
    }

    public function test_opening_a_conversation_marks_only_its_conversation_notifications_as_read(): void
    {
        [$member, , $conversation] = $this->conversationMembers();
        $otherConversation = $this->conversationFor($member);
        $message = $this->notification($member, 'conversations', $conversation);
        $match = $this->notification($member, 'conversations', $conversation, 'notifications.items.new_match');
        $other = $this->notification($member, 'conversations', $otherConversation);
        $event = $this->notification($member, 'events', $conversation, 'notifications.items.event_changed');

        $this->actingAs($member)
            ->get(route('conversations.show', $conversation))
            ->assertOk();

        expect($message->fresh()?->read_at)->not->toBeNull()
            ->and($match->fresh()?->read_at)->not->toBeNull()
            ->and($other->fresh()?->read_at)->toBeNull()
            ->and($event->fresh()?->read_at)->toBeNull();
    }

    public function test_conversation_read_is_idempotent_and_ignores_legacy_notifications_without_a_target(): void
    {
        [$member, , $conversation] = $this->conversationMembers();
        $targeted = $this->notification($member, 'conversations', $conversation);
        $legacy = $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'category' => 'conversations',
                'translation_key' => 'notifications.items.new_message',
                'parameters' => ['sender' => 'Ami'],
            ],
        ]);

        $this->actingAs($member)
            ->post(route('conversations.read.store', $conversation))
            ->assertNoContent();
        $firstReadAt = $targeted->fresh()?->read_at;

        $this->travel(1)->second();
        $this->actingAs($member)
            ->post(route('conversations.read.store', $conversation))
            ->assertNoContent();

        expect($firstReadAt)->not->toBeNull()
            ->and($targeted->fresh()?->read_at?->equalTo($firstReadAt))->toBeTrue()
            ->and($legacy->fresh()?->read_at)->toBeNull();
    }

    public function test_reading_an_event_notification_opens_the_canonical_event_panel_workspace(): void
    {
        $member = User::factory()->withProfile()->create();
        $event = Event::factory()->create();
        $notification = $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'category' => 'events',
                'translation_key' => 'notifications.items.event_changed',
                'parameters' => ['event' => $event->title],
                'target_type' => 'event',
                'target_id' => $event->id,
            ],
        ]);

        $this->actingAs($member)
            ->patch(route('notifications.read', $notification))
            ->assertRedirect(route('events.show', $event, absolute: false));

        $this->actingAs($member)
            ->get(route('events.show', $event))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Index')
                ->where('context', 'discover')
                ->where('panel.kind', 'detail')
                ->where('panel.event.id', $event->id));
    }

    public function test_a_missing_event_target_falls_back_to_the_notification_center(): void
    {
        $member = User::factory()->withProfile()->create();
        $event = Event::factory()->create();
        $notification = $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'category' => 'events',
                'translation_key' => 'notifications.items.event_cancelled',
                'parameters' => ['event' => $event->title],
                'target_type' => 'event',
                'target_id' => $event->id,
            ],
        ]);
        $event->delete();

        $this->actingAs($member)
            ->patch(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'));
    }

    public function test_mark_all_read_does_not_change_another_members_notifications(): void
    {
        [$member, $peer, $conversation] = $this->conversationMembers();
        $mine = $this->notification($member, 'conversations', $conversation);
        $theirs = $this->notification($peer, 'conversations', $conversation);

        $this->actingAs($member)
            ->patch(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));

        expect($mine->fresh()?->read_at)->not->toBeNull()
            ->and($theirs->fresh()?->read_at)->toBeNull();
    }

    public function test_a_member_can_mark_their_notification_read_without_navigation(): void
    {
        [$member, , $conversation] = $this->conversationMembers();
        $notification = $this->notification($member, 'conversations', $conversation);

        $this->actingAs($member)
            ->from(route('notifications.index'))
            ->patch(route('notifications.mark-read', $notification))
            ->assertRedirect(route('notifications.index'));

        expect($notification->fresh()?->read_at)->not->toBeNull();
    }

    public function test_notification_mutations_are_scoped_to_the_owner(): void
    {
        [$member, , $conversation] = $this->conversationMembers();
        $other = User::factory()->withProfile()->create();
        $notification = $this->notification($member, 'conversations', $conversation);

        $this->actingAs($other)
            ->patch(route('notifications.mark-read', $notification))
            ->assertNotFound();
        $this->actingAs($other)
            ->delete(route('notifications.destroy', $notification))
            ->assertNotFound();

        expect($notification->fresh())->not->toBeNull()
            ->and($notification->fresh()?->read_at)->toBeNull();
    }

    public function test_deleting_a_notification_keeps_its_conversation_message_and_match(): void
    {
        [$member, $peer, $conversation] = $this->conversationMembers();
        $message = Message::factory()->for($conversation)->for($peer, 'author')->create();
        $matchId = $conversation->match_id;
        $notification = $this->notification($member, 'conversations', $conversation);

        $this->actingAs($member)
            ->from(route('notifications.index'))
            ->delete(route('notifications.destroy', $notification))
            ->assertRedirect(route('notifications.index'));

        expect($notification->fresh())->toBeNull()
            ->and($conversation->fresh())->not->toBeNull()
            ->and($message->fresh())->not->toBeNull()
            ->and(MemberMatch::find($matchId))->not->toBeNull();
    }

    public function test_the_shared_auth_payload_contains_the_member_unread_count(): void
    {
        [$member, , $conversation] = $this->conversationMembers();
        $this->notification($member, 'conversations', $conversation);
        $read = $this->notification($member, 'conversations', $conversation);
        $read->markAsRead();

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.unread_notifications_count', 1));
    }

    public function test_a_missing_target_falls_back_to_the_notification_center(): void
    {
        [$member, , $conversation] = $this->conversationMembers();
        $notification = $this->notification($member, 'conversations', $conversation);
        $conversation->delete();

        $this->actingAs($member)
            ->patch(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'));
    }

    private function notification(
        User $user,
        string $category,
        Conversation $conversation,
        string $translationKey = 'notifications.items.new_message',
    ): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'category' => $category,
                'translation_key' => $translationKey,
                'parameters' => ['sender' => 'Ami'],
                'target_type' => 'conversation',
                'target_id' => $conversation->id,
            ],
        ]);
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

    private function conversationFor(User $member): Conversation
    {
        $peer = User::factory()->withProfile()->create();
        [$lowUser, $highUser] = $member->id < $peer->id
            ? [$member, $peer]
            : [$peer, $member];
        $match = MemberMatch::factory()->create([
            'user_low_id' => $lowUser->id,
            'user_high_id' => $highUser->id,
        ]);

        return $match->conversation()->create();
    }
}
