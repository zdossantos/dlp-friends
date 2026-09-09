<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MemberMatch;
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
            ->patch(route('notifications.read', $notification))
            ->assertRedirect(route('conversations.show', $conversation, absolute: false));
        expect($notification->fresh()?->read_at)->not->toBeNull();
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

    private function notification(User $user, string $category, Conversation $conversation): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'category' => $category,
                'translation_key' => 'notifications.items.new_message',
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
}
