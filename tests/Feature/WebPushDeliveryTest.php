<?php

use App\Actions\DeliverWebPushNotification;
use App\Contracts\WebPushTransport;
use App\Enums\WebPushPreference;
use App\Jobs\SendWebPushNotification;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\WebPushDelivery;
use App\Models\WebPushSubscription;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\NewMessageNotification;
use App\Support\WebPushResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;

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

test('a successful transport response records one delivery without private message content', function () {
    [, $recipient, $message] = pushConversation();
    $subscription = WebPushSubscription::factory()->for($recipient)->create();
    $notification = new NewMessageNotification($message);
    $notification->id = (string) Str::uuid();

    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldReceive('send')->once()->withArgs(function ($device, array $payload, string $topic) use ($subscription, $message): bool {
        expect($device->is($subscription))->toBeTrue()
            ->and($payload['notification_id'])->toBeString()
            ->and(json_encode($payload))->not->toContain($message->content)
            ->and($topic)->toHaveLength(32);

        return true;
    })->andReturn(new WebPushResult(true, false, 201));

    (new DeliverWebPushNotification($transport))->handle($subscription, $notification);

    $this->assertDatabaseHas('web_push_deliveries', [
        'notification_id' => $notification->id,
        'web_push_subscription_id' => $subscription->id,
        'status' => 'delivered',
        'attempts' => 1,
        'last_status_code' => 201,
    ]);
});

test('a push uses concise copy in the account language without repeating the app name', function (string $locale, string $title, string $body) {
    [, $recipient, $message] = pushConversation();
    $recipient->update(['locale' => $locale]);
    $subscription = WebPushSubscription::factory()->for($recipient)->create();
    $notification = new NewMessageNotification($message);
    $notification->id = (string) Str::uuid();

    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldReceive('send')->once()->withArgs(function ($device, array $payload) use ($subscription, $locale, $title, $body): bool {
        expect($device->is($subscription))->toBeTrue()
            ->and($payload['title'])->toBe($title)
            ->and($payload['title'])->not->toBe('DLP Friends')
            ->and($payload['body'])->toBe($body)
            ->and($payload['locale'])->toBe($locale);

        return true;
    })->andReturn(new WebPushResult(true, false, 201));

    (new DeliverWebPushNotification($transport))->handle($subscription, $notification);
})->with([
    'French account' => ['fr', 'Nouveau message', 'Un nouveau message t’attend.'],
    'English account' => ['en', 'New message', 'A new message is waiting for you.'],
]);

test('an expired endpoint is revoked permanently', function () {
    [, $recipient, $message] = pushConversation();
    $subscription = WebPushSubscription::factory()->for($recipient)->create();
    $notification = new NewMessageNotification($message);
    $notification->id = (string) Str::uuid();
    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldReceive('send')->once()->andReturn(new WebPushResult(false, true, 410));

    (new DeliverWebPushNotification($transport))->handle($subscription, $notification);

    expect($subscription->fresh()->revoked_at)->not->toBeNull();
    $this->assertDatabaseHas('web_push_deliveries', [
        'notification_id' => $notification->id,
        'status' => 'permanent_failure',
        'last_status_code' => 410,
    ]);
});

test('a missing endpoint is revoked permanently', function () {
    [, $recipient, $message] = pushConversation();
    $subscription = WebPushSubscription::factory()->for($recipient)->create();
    $notification = new NewMessageNotification($message);
    $notification->id = (string) Str::uuid();
    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldReceive('send')->once()->andReturn(new WebPushResult(false, true, 404));

    (new DeliverWebPushNotification($transport))->handle($subscription, $notification);

    expect($subscription->fresh()->revoked_at)->not->toBeNull();
    $this->assertDatabaseHas('web_push_deliveries', [
        'notification_id' => $notification->id,
        'status' => 'permanent_failure',
        'last_status_code' => 404,
    ]);
});

test('a rate-limited endpoint is retained for retry', function () {
    [, $recipient, $message] = pushConversation();
    $subscription = WebPushSubscription::factory()->for($recipient)->create();
    $notification = new NewMessageNotification($message);
    $notification->id = (string) Str::uuid();
    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldReceive('send')->once()->andReturn(new WebPushResult(false, false, 429));

    expect(fn () => (new DeliverWebPushNotification($transport))->handle($subscription, $notification))
        ->toThrow(RuntimeException::class);

    expect($subscription->fresh()->revoked_at)->toBeNull();
    $this->assertDatabaseHas('web_push_deliveries', [
        'notification_id' => $notification->id,
        'status' => 'retrying',
        'last_status_code' => 429,
    ]);
});

test('a server failure retains the endpoint for retry', function () {
    [, $recipient, $message] = pushConversation();
    $subscription = WebPushSubscription::factory()->for($recipient)->create();
    $notification = new NewMessageNotification($message);
    $notification->id = (string) Str::uuid();
    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldReceive('send')->once()->andReturn(new WebPushResult(false, false, 503));

    expect(fn () => (new DeliverWebPushNotification($transport))->handle($subscription, $notification))
        ->toThrow(RuntimeException::class);

    expect($subscription->fresh()->revoked_at)->toBeNull();
    $this->assertDatabaseHas('web_push_deliveries', [
        'notification_id' => $notification->id,
        'status' => 'retrying',
        'last_status_code' => 503,
    ]);
});

test('a completed delivery is idempotent across repeated jobs', function () {
    [, $recipient, $message] = pushConversation();
    $subscription = WebPushSubscription::factory()->for($recipient)->create();
    $notification = new NewMessageNotification($message);
    $notification->id = (string) Str::uuid();
    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldReceive('send')->once()->andReturn(new WebPushResult(true, false, 201));
    $deliver = new DeliverWebPushNotification($transport);

    $deliver->handle($subscription, $notification);
    $deliver->handle($subscription, $notification);

    expect(WebPushDelivery::query()->where('notification_id', $notification->id)->count())->toBe(1);
});

test('an abandoned sending lease can be recovered', function () {
    [, $recipient, $message] = pushConversation();
    $subscription = WebPushSubscription::factory()->for($recipient)->create();
    $notification = new NewMessageNotification($message);
    $notification->id = (string) Str::uuid();
    $delivery = WebPushDelivery::query()->create([
        'notification_id' => $notification->id,
        'web_push_subscription_id' => $subscription->id,
        'category' => WebPushPreference::Messages,
        'status' => 'sending',
        'attempts' => 1,
    ]);
    WebPushDelivery::query()->whereKey($delivery->id)->update(['updated_at' => now()->subMinutes(11)]);
    $transport = Mockery::mock(WebPushTransport::class);
    $transport->shouldReceive('send')->once()->andReturn(new WebPushResult(true, false, 201));

    (new DeliverWebPushNotification($transport))->handle($subscription, $notification);

    $this->assertDatabaseHas('web_push_deliveries', [
        'notification_id' => $notification->id,
        'status' => 'delivered',
        'attempts' => 2,
    ]);
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
