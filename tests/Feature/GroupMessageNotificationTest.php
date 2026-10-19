<?php

use App\Actions\DeliverWebPushNotification;
use App\Actions\MarkEventChatRead;
use App\Actions\SendEventChatMessage;
use App\Contracts\WebPushTransport;
use App\Enums\EventRegistrationStatus;
use App\Events\EventChatMessageSent;
use App\Models\Event;
use App\Models\EventChat;
use App\Models\EventChatMessage;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Notifications\NewGroupMessageNotification;
use App\Support\MemberNotificationPresenter;
use App\Support\WebPushResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function groupNotificationScenario(): array
{
    $organizer = User::factory()->withProfile()->create();
    $member = User::factory()->withProfile()->create();
    $event = Event::factory()->for($organizer, 'organizer')->create();
    $chat = EventChat::factory()->for($event)->create();
    EventRegistration::factory()->accepted()->for($event)->for($member)->create();

    return [$event, $chat, $organizer, $member];
}

test('a group message creates a private notification only for other accepted members', function () {
    [$event, $chat, $organizer, $member] = groupNotificationScenario();
    foreach ([EventRegistrationStatus::Pending, EventRegistrationStatus::Removed, EventRegistrationStatus::Blocked] as $status) {
        EventRegistration::factory()->for($event)->create(['status' => $status]);
    }

    app(SendEventChatMessage::class)->handle($organizer, $chat, 'Contenu du groupe');

    $notification = $member->notifications()->sole();
    expect($notification->data['target_type'])->toBe('event_chat')
        ->and($notification->data['target_id'])->toBe($event->id)
        ->and($notification->data['category'])->toBe('conversations')
        ->and(json_encode($notification->data))->not->toContain('Contenu du groupe')
        ->and($organizer->notifications()->count())->toBe(0);
    $this->assertDatabaseCount('notifications', 1);
    expect(app(MemberNotificationPresenter::class)->targetUrl($notification->data, $member))
        ->toBe(route('events.chat.show', $event, absolute: false));
});

test('a participant notifies the organizer and group preferences can disable and restore alerts', function () {
    [$event, $chat, $organizer, $member] = groupNotificationScenario();
    $this->actingAs($organizer)->patch(route('notification-preferences.update'), ['group_messages' => false])->assertRedirect();
    app(SendEventChatMessage::class)->handle($member, $chat, 'Premier message');
    expect($organizer->notifications()->count())->toBe(0);

    $this->actingAs($organizer)->patch(route('notification-preferences.update'), ['group_messages' => true])->assertRedirect();
    app(SendEventChatMessage::class)->handle($member, $chat, 'Deuxième message');
    expect($organizer->notifications()->count())->toBe(1);
});

test('reading a group acknowledges only notifications through the read cursor and allows deletion', function () {
    [$event, $chat, $organizer, $member] = groupNotificationScenario();
    $older = app(SendEventChatMessage::class)->handle($organizer, $chat, 'Ancien');
    app(SendEventChatMessage::class)->handle($organizer, $chat, 'Nouveau');
    $this->actingAs($member)->postJson(route('events.chat.read.store', $event), ['last_read_message_id' => $older->id])->assertNoContent();
    expect($member->notifications()->whereNotNull('read_at')->count())->toBe(1)
        ->and($member->unreadNotifications()->count())->toBe(1);
    $notification = $member->notifications()->oldest()->first();
    $this->delete(route('notifications.destroy', $notification))->assertRedirect();
    expect($member->notifications()->count())->toBe(1);
});

test('queued group alerts recheck membership and preference before delivery', function () {
    [$event, $chat, $organizer, $member] = groupNotificationScenario();
    $message = EventChatMessage::factory()->for($chat)->for($organizer, 'author')->create();
    $notification = new NewGroupMessageNotification($message);
    expect($notification->shouldSend($member, 'database'))->toBeTrue();
    EventRegistration::query()->where('user_id', $member->id)->update(['status' => EventRegistrationStatus::Removed]);
    expect($notification->shouldSend($member, 'database'))->toBeFalse()
        ->and($notification->webPushAccessAllowed($member))->toBeFalse();
});

test('group activity reaches only eligible personal channels even with alerts disabled', function () {
    [$event, $chat, $organizer, $member] = groupNotificationScenario();
    $removed = User::factory()->withProfile()->create();
    EventRegistration::factory()->for($event)->for($removed)->create(['status' => EventRegistrationStatus::Removed]);
    $member->notificationPreferences()->create(['category' => 'group_messages', 'enabled' => false]);
    $message = EventChatMessage::factory()->for($chat)->for($organizer, 'author')->create();
    $channels = collect((new EventChatMessageSent($message))->broadcastOn())->pluck('name')->all();
    expect($channels)->toContain("private-App.Models.User.{$member->id}")
        ->not->toContain("private-App.Models.User.{$removed->id}");
});

test('reading before queued delivery suppresses the late notification', function () {
    [$event, $chat, $organizer, $member] = groupNotificationScenario();
    $message = EventChatMessage::factory()->for($chat)->for($organizer, 'author')->create();
    $notification = new NewGroupMessageNotification($message);
    app(MarkEventChatRead::class)->handle($member, $chat, $message->id);
    expect($notification->shouldSend($member, 'database'))->toBeFalse()
        ->and($notification->webPushAccessAllowed($member))->toBeFalse();
});

test('group push delivery opens the chat and respects an independent preference', function () {
    [$event, $chat, $organizer, $member] = groupNotificationScenario();
    $message = EventChatMessage::factory()->for($chat)->for($organizer, 'author')->create(['content' => str_repeat('Bonjour ', 25)]);
    $notification = new NewGroupMessageNotification($message);
    $notification->id = (string) Str::uuid();
    $subscription = WebPushSubscription::factory()->for($member)->create();
    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldReceive('send')->once()->withArgs(function ($device, array $payload) use ($event): bool {
        expect($payload['target'])->toBe("/events/{$event->id}/chat")
            ->and(mb_strlen($payload['body']))->toBeLessThanOrEqual(101);

        return true;
    })->andReturn(new WebPushResult(true, false, 201));
    $member->notificationPreferences()->create(['category' => 'messages', 'enabled' => false]);
    $deliver = new DeliverWebPushNotification($transport);
    $deliver->handle($subscription, $notification);
    $member->notificationPreferences()->create(['category' => 'group_messages', 'enabled' => false]);
    $notification->id = (string) Str::uuid();
    $deliver->handle($subscription, $notification);
    $this->assertDatabaseCount('web_push_deliveries', 1);
});

test('database delivery rechecks a read occurring after the queued preflight', function () {
    [$event, $chat, $organizer, $member] = groupNotificationScenario();
    $message = EventChatMessage::factory()->for($chat)->for($organizer, 'author')->create();
    $notification = new NewGroupMessageNotification($message);
    $notification->id = (string) Str::uuid();
    expect($notification->shouldSend($member, 'database'))->toBeTrue();

    app(MarkEventChatRead::class)->handle($member, $chat, $message->id);
    app(ChannelManager::class)->driver($notification->via($member)[0])->send($member, $notification);

    expect($member->unreadNotifications()->count())->toBe(0);
});
