<?php

use App\Actions\DeliverWebPushNotification;
use App\Actions\MarkGroupChatRead;
use App\Contracts\WebPushTransport;
use App\Events\GroupMessageNotificationBroadcast;
use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Notifications\GroupChatMessageLikedNotification;
use App\Notifications\NewGroupMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('prepared group notifications recheck access at actual database broadcast and push delivery', function (string $revocation) {
    $organizer = User::factory()->withProfile()->create();
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($organizer, 'organizer')->create(['audience' => 'members']);
    $chat = GroupChat::query()->create(['trip_id' => $trip->id]);
    $registration = TripRegistration::factory()->for($trip)->for($member, 'user')->create(['status' => 'accepted']);
    $message = GroupChatMessage::factory()->for($chat)->for($organizer, 'author')->create();
    $notification = new NewGroupMessageNotification($message);
    $notification->id = (string) Str::uuid();
    $subscription = WebPushSubscription::factory()->for($member)->create();
    $broadcast = unserialize(serialize(new GroupMessageNotificationBroadcast($member->id, $notification)));
    expect($broadcast->broadcastOn())->not->toBeEmpty();
    if ($revocation === 'removed') {
        $registration->update(['status' => 'removed']);
    } elseif ($revocation === 'blocked') {
        $member->blocksCreated()->create(['blocked_user_id' => $organizer->id]);
    } elseif ($revocation === 'read') {
        app(MarkGroupChatRead::class)->handle($member, $chat, $message->id);
    } elseif ($revocation === 'deleted') {
        $trip->delete();
    } elseif ($revocation === 'preference') {
        $member->notificationPreferences()->create(['category' => 'group_messages', 'enabled' => false]);
    } else {
        $organizer->forceFill(['status' => 'banned'])->save();
    }
    expect($broadcast->broadcastOn())->toBe([])->and($broadcast->broadcastWith())->toBe([]);
    app(ChannelManager::class)->driver($notification->via($member)[0])->send($member, unserialize(serialize($notification)));
    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldNotReceive('send');
    (new DeliverWebPushNotification($transport))->handle($subscription, unserialize(serialize($notification)));
    expect($member->unreadNotifications()->count())->toBe(0);
})->with(['removed', 'blocked', 'read', 'deleted', 'preference', 'organizer_banned']);

test('a prepared reaction notification is suppressed after its reaction or group access disappears', function (string $change) {
    $organizer = User::factory()->withProfile()->create();
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($organizer, 'organizer')->create(['audience' => 'members']);
    $chat = GroupChat::query()->create(['trip_id' => $trip->id]);
    $registration = TripRegistration::factory()->for($trip)->for($member, 'user')->create(['status' => 'accepted']);
    $message = GroupChatMessage::factory()->for($chat)->for($member, 'author')->create();
    $reaction = $message->reactions()->create(['user_id' => $organizer->id]);
    $notification = new GroupChatMessageLikedNotification($message, $organizer);
    $notification->id = (string) Str::uuid();
    $broadcast = unserialize(serialize(new GroupMessageNotificationBroadcast($member->id, $notification)));
    expect($broadcast->broadcastOn())->not->toBeEmpty();
    match ($change) {
        'unlike' => $reaction->delete(),
        'removed' => $registration->update(['status' => 'removed']),
        'deleted' => $trip->delete(),
        'inactive' => $organizer->forceFill(['status' => 'banned'])->save(),
        'preference' => $member->notificationPreferences()->create(['category' => 'group_messages', 'enabled' => false]),
    };
    expect($broadcast->broadcastOn())->toBe([])->and($broadcast->broadcastWith())->toBe([]);
    app(ChannelManager::class)->driver($notification->via($member)[0])->send($member, unserialize(serialize($notification)));
    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldNotReceive('send');
    (new DeliverWebPushNotification($transport))->handle(WebPushSubscription::factory()->for($member)->create(), unserialize(serialize($notification)));
    expect($member->unreadNotifications()->count())->toBe(0);
})->with(['unlike', 'removed', 'deleted', 'inactive', 'preference']);

test('retrying the same queued notification does not duplicate its database entry', function () {
    $organizer = User::factory()->withProfile()->create();
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($organizer, 'organizer')->create(['audience' => 'members']);
    $chat = GroupChat::query()->create(['trip_id' => $trip->id]);
    TripRegistration::factory()->for($trip)->for($member, 'user')->create(['status' => 'accepted']);
    $message = GroupChatMessage::factory()->for($chat)->for($organizer, 'author')->create();
    $notification = new NewGroupMessageNotification($message);
    $notification->id = (string) Str::uuid();
    $channel = app(ChannelManager::class)->driver($notification->via($member)[0]);
    $channel->send($member, $notification);
    $channel->send($member, unserialize(serialize($notification)));
    expect($member->unreadNotifications()->count())->toBe(1);
});
