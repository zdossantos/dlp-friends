<?php

use App\Enums\WebPushPreference;
use App\Jobs\SendWebPushNotification;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('the channel queues one delivery per active device only when configured and enabled', function () {
    Queue::fake();
    config()->set('services.web_push', [
        'subject' => 'mailto:test@example.test', 'public_key' => 'public', 'private_key' => 'private',
    ]);
    [$sender, $recipient, $message] = pushConversation();
    WebPushSubscription::factory()->count(2)->for($recipient)->create();
    WebPushSubscription::factory()->for($recipient)->create(['revoked_at' => now()]);
    $notification = new NewMessageNotification($message);

    app(WebPushChannel::class)->send($recipient, $notification);

    Queue::assertPushed(SendWebPushNotification::class, 2);

    NotificationPreference::query()->create([
        'user_id' => $recipient->id, 'category' => WebPushPreference::Messages, 'enabled' => false,
    ]);
    app(WebPushChannel::class)->send($recipient, $notification);
    Queue::assertPushed(SendWebPushNotification::class, 2);
});

test('missing vapid configuration never queues a delivery', function () {
    Queue::fake();
    config()->set('services.web_push', ['subject' => null, 'public_key' => null, 'private_key' => null]);
    [, $recipient, $message] = pushConversation();
    WebPushSubscription::factory()->for($recipient)->create();

    app(WebPushChannel::class)->send($recipient, new NewMessageNotification($message));

    Queue::assertNothingPushed();
});

/** @return array{User, User, Message} */
function pushConversation(): array
{
    $first = User::factory()->withProfile()->create();
    $second = User::factory()->withProfile()->create();
    [$low, $high] = $first->id < $second->id ? [$first, $second] : [$second, $first];
    $match = MemberMatch::factory()->create(['user_low_id' => $low->id, 'user_high_id' => $high->id]);
    $conversation = $match->conversation()->create();
    $message = Message::factory()->for($conversation)->for($low, 'author')->create();

    return [$low, $high, $message];
}
