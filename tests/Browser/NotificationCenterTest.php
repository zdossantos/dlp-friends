<?php

use App\Models\Event;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(fn () => Storage::fake('local'));

test('a member filters notifications and opens the related conversation', function () {
    $member = notificationBrowserMember('Alice');
    $peer = notificationBrowserMember('Basile');
    [$lowId, $highId] = collect([$member->id, $peer->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create([
        'user_low_id' => $lowId,
        'user_high_id' => $highId,
    ]);
    $conversation = $match->conversation()->create();
    $conversationNotification = notificationBrowserNotice(
        $member,
        'conversations',
        'Basile',
        $conversation->id,
    );
    $eventNotification = notificationBrowserNotice(
        $member,
        'events',
        'Camille',
        $conversation->id,
    );
    $eventNotification->markAsRead();
    $this->actingAs($member);

    $page = visit('/notifications')->on()->mobile()
        ->assertSee('Notifications')
        ->assertSee('Basile')
        ->assertSee('Camille')
        ->assertPresent('[aria-label="Notifications"][aria-current="page"]')
        ->assertPresent('[data-test="notification-unread-count"]')
        ->assertNoJavaScriptErrors();

    $page->click('[data-test="notification-filter-unread"]')
        ->assertSee('Basile')
        ->assertDontSee('Camille')
        ->click('[data-test="notification-'.$conversationNotification->id.'"]')
        ->assertPathIs("/conversations/{$conversation->id}")
        ->assertNoJavaScriptErrors();

    expect($conversationNotification->fresh()?->read_at)->not->toBeNull();
});

test('an event notification opens its detail over the discover workspace', function () {
    $member = notificationBrowserMember('Alice');
    $event = Event::factory()->create(['title' => 'Balade du soir']);
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
    $this->actingAs($member);

    visit('/notifications')
        ->click('[data-test="notification-'.$notification->id.'"]')
        ->assertPathIs("/events/{$event->id}")
        ->assertPresent('[data-test="discover-events"]')
        ->assertPresent('[data-test="event-panel"]')
        ->assertPresent('[data-test="event-detail"]')
        ->assertNoJavaScriptErrors();

    expect($notification->fresh()?->read_at)->not->toBeNull();
});

function notificationBrowserMember(string $displayName): User
{
    $user = User::factory()->withProfile()->create();
    $user->profile?->update(['display_name' => $displayName]);
    Storage::disk('local')->put($user->profile->avatar->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));

    return $user;
}

function notificationBrowserNotice(
    User $member,
    string $category,
    string $sender,
    int $conversationId,
): DatabaseNotification {
    return $member->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => [
            'category' => $category,
            'translation_key' => 'notifications.items.new_message',
            'parameters' => ['sender' => $sender],
            'target_type' => 'conversation',
            'target_id' => $conversationId,
        ],
    ]);
}
