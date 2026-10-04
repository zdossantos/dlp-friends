<?php

use App\Actions\RevokeMemberRealtimeAccess;
use App\Broadcasting\IdentityAwareReverbBroadcaster;
use App\Models\MemberMatch;
use App\Models\User;
use App\Reverb\IdentityAwareChannelManager;
use App\Reverb\IdentityAwarePrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\PusherPubSubIncomingMessageHandler;

uses(RefreshDatabase::class);

it('attaches signed identity to every channel and denies delivery and replay after a ban', function (bool $except) {
    $this->app->bind(ChannelConnectionManager::class, ArrayChannelConnectionManager::class);
    $user = User::factory()->create();
    $app = new Application('test', 'key', 'secret', 60, 30, ['*'], 10000);
    $socket = new class($app) extends Connection
    {
        public array $received = [];

        public bool $closed = false;

        public function __construct(Application $application)
        {
            $this->application = $application;
        }

        public function identifier(): string
        {
            return '123';
        }

        public function id(): string
        {
            return '123.456';
        }

        public function send(string $message): void
        {
            $this->received[] = $message;
        }

        public function control(string $type = '9'): void {}

        public function terminate(): void
        {
            $this->closed = true;
        }
    };
    $channel = new IdentityAwarePrivateChannel('private-conversation.12');
    $data = json_encode(['user_id' => (string) $user->id]);
    $auth = 'key:'.hash_hmac('sha256', $socket->id().':'.$channel->name().':'.$data, 'secret');
    $channel->subscribe($socket, $auth, $data);
    $channel->broadcast(['event' => 'message', 'data' => 'before']);
    expect($socket->received)->toHaveCount(1);
    $user->forceFill(['status' => 'banned'])->save();
    $channel->broadcast(['event' => 'message', 'data' => 'after'], $except ? $socket : null);
    expect($socket->received)->toHaveCount(1)->and($socket->closed)->toBeTrue();
    expect(fn () => $channel->subscribe($socket, $auth, $data))->toThrow(ConnectionUnauthorized::class);
})->with([false, true]);

it('requires identity and an intact signature on private subscriptions', function (string $variant) {
    $this->app->bind(ChannelConnectionManager::class, ArrayChannelConnectionManager::class);
    $user = User::factory()->create();
    $application = new Application('test', 'key', 'secret', 60, 30, ['*'], 10000);
    $socket = new class($application) extends Connection
    {
        public function __construct(Application $application)
        {
            $this->application = $application;
        }

        public function identifier(): string
        {
            return '1';
        }

        public function id(): string
        {
            return '1.2';
        }

        public function send(string $message): void {}

        public function control(string $type = '9'): void {}

        public function terminate(): void {}
    };
    $channel = new IdentityAwarePrivateChannel('private-conversation.1');
    $data = $variant === 'missing' ? null : json_encode(['user_id' => (string) $user->id]);
    $auth = 'key:'.hash_hmac('sha256', $socket->id().':'.$channel->name().($data === null ? '' : ':'.$data), 'secret');
    if ($variant === 'tampered') {
        $data = json_encode(['user_id' => (string) ($user->id + 1)]);
    }
    if ($variant === 'inactive') {
        $user->forceFill(['status' => 'banned'])->save();
    }
    expect(fn () => $channel->subscribe($socket, $auth, $data))->toThrow(ConnectionUnauthorized::class);
})->with(['missing', 'tampered', 'inactive']);

it('signs channel identity only after Laravel authorizes the participant', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'key', 'broadcasting.connections.reverb.secret' => 'secret', 'broadcasting.connections.reverb.app_id' => 'test']);
    Broadcast::purge();
    require base_path('routes/channels.php');
    $member = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create();
    $conversation = MemberMatch::query()->create(['user_low_id' => $member->id, 'user_high_id' => $other->id])->conversation()->create();
    $response = $this->actingAs($member)->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => "private-conversation.{$conversation->id}"])->assertOk();
    expect(json_decode($response->json('channel_data'), true))->toBe(['user_id' => (string) $member->id]);
    $this->actingAs(User::factory()->create())->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => "private-conversation.{$conversation->id}"])->assertForbidden();
    $member->forceFill(['status' => 'banned'])->save();
    $this->actingAs($member)->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => "private-conversation.{$conversation->id}"])->assertForbidden();
});

it('filters every channel on a distributed node even if HTTP termination fails', function () {
    $this->app->bind(ChannelConnectionManager::class, ArrayChannelConnectionManager::class);
    $manager = new IdentityAwareChannelManager;
    $this->app->instance(ChannelManager::class, $manager);
    $user = User::factory()->create();
    $application = new Application('test', 'key', 'secret', 60, 30, ['*'], 10000);
    $socket = new class($application) extends Connection
    {
        public array $received = [];

        public bool $closed = false;

        public function __construct(Application $application)
        {
            $this->application = $application;
        }

        public function identifier(): string
        {
            return '1';
        }

        public function id(): string
        {
            return '1.2';
        }

        public function send(string $message): void
        {
            $this->received[] = $message;
        }

        public function control(string $type = '9'): void {}

        public function terminate(): void
        {
            $this->closed = true;
        }
    };
    $channels = ['private-conversation.1', 'private-event-chat.1', "private-App.Models.User.{$user->id}"];
    foreach ($channels as $name) {
        $channel = $manager->for($application)->findOrCreate($name);
        $data = json_encode(['user_id' => (string) $user->id]);
        $channel->subscribe($socket, 'key:'.hash_hmac('sha256', $socket->id().':'.$name.':'.$data, 'secret'), $data);
        $channel->broadcastToAll(['event' => 'before', 'data' => 'visible']);
    }
    expect($socket->received)->toHaveCount(3);
    $user->forceFill(['status' => 'banned'])->save();
    config(['broadcasting.default' => 'reverb']);
    $pusher = Mockery::mock(Pusher\Pusher::class);
    $pusher->shouldReceive('terminateUserConnections')->once()->with((string) $user->id)->andThrow(new RuntimeException('Private error must not be logged'));
    $broadcaster = new IdentityAwareReverbBroadcaster($pusher);
    Broadcast::shouldReceive('connection')->with('reverb')->once()->andReturn($broadcaster);
    Log::shouldReceive('warning')->once()->with('Realtime account revocation unavailable.');
    app(RevokeMemberRealtimeAccess::class)->handle($user);
    (new PusherPubSubIncomingMessageHandler)->handle(json_encode(['type' => 'message', 'application' => serialize($application), 'payload' => ['channels' => $channels, 'event' => 'after', 'data' => 'must not arrive']]));
    expect($socket->received)->toHaveCount(3)->and($socket->closed)->toBeTrue();
});
