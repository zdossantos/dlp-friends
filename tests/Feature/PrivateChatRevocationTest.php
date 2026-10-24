<?php

use App\Actions\BlockUser;
use App\Enums\ProfileVisibility;
use App\Events\MessageReactionUpdated;
use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use App\Reverb\IdentityAwareChannelManager;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Protocols\Pusher\ClientEvent;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelConnectionManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->author = User::factory()->withProfile()->create();
    $this->peer = User::factory()->withProfile()->create();
    $this->conversation = MemberMatch::query()->create([
        'user_low_id' => $this->author->id,
        'user_high_id' => $this->peer->id,
    ])->conversation()->create();
    $this->message = Message::factory()->for($this->conversation)->for($this->author, 'author')->create();
});

test('queued private events stop after a block or archive even with previously loaded relations', function (string $change) {
    $this->message->load('conversation.memberMatch.lowUser', 'conversation.memberMatch.highUser');
    $events = [
        new MessageSent($this->message),
        new MessageReactionUpdated($this->conversation->id, $this->message->id, $this->peer->id, 1, true),
        new MessagesRead($this->conversation->id, $this->peer->id, $this->message->id, now()->toISOString()),
    ];
    $jobs = array_map(fn ($event) => unserialize(serialize(new BroadcastEvent($event))), $events);

    if ($change === 'archive') {
        $this->conversation->update(['archived_at' => now()]);
    } elseif ($change === 'block-only') {
        Block::query()->create(['blocker_user_id' => $this->peer->id, 'blocked_user_id' => $this->author->id]);
    } else {
        [$blocker, $blocked] = $change === 'author-blocks' ? [$this->author, $this->peer] : [$this->peer, $this->author];
        app(BlockUser::class)->handle($blocker, $blocked);
    }

    $manager = Mockery::mock(Factory::class);
    $manager->shouldNotReceive('connection');
    foreach ($events as $event) {
        expect($event->broadcastWhen())->toBeFalse();
    }
    foreach ($jobs as $job) {
        $job->handle($manager);
    }
})->with(['author-blocks', 'peer-blocks', 'block-only', 'archive']);

test('private delivery preserves existing conversations when a profile is hidden', function () {
    $this->peer->profile->update(['visibility' => ProfileVisibility::Hidden]);

    expect((new MessageSent($this->message))->broadcastOn())->toHaveCount(3)
        ->and((new MessageReactionUpdated($this->conversation->id, $this->message->id, $this->peer->id, 1, true))->broadcastWhen())->toBeTrue()
        ->and((new MessagesRead($this->conversation->id, $this->peer->id, $this->message->id, now()->toISOString()))->broadcastWhen())->toBeTrue();
});

test('open conversation channels reject whispers delivery and signed replay after revocation', function (string $change, bool $fromPeer) {
    $this->app->bind(ChannelConnectionManager::class, ArrayChannelConnectionManager::class);
    $manager = new IdentityAwareChannelManager;
    $this->app->instance(ChannelManager::class, $manager);
    $application = new Application('test', 'key', 'secret', 60, 30, ['*'], 10000);
    $channel = $manager->for($application)->findOrCreate('private-conversation.'.$this->conversation->id);
    $sockets = [];
    $credentials = [];
    $personalChannels = [];
    foreach ([$this->author, $this->peer] as $member) {
        $socket = new class($application, (string) $member->id) extends Connection
        {
            public array $received = [];

            public bool $closed = false;

            public function __construct(Application $application, private string $socketId)
            {
                $this->application = $application;
            }

            public function identifier(): string
            {
                return $this->socketId;
            }

            public function id(): string
            {
                return $this->socketId.'.1';
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
        $data = json_encode(['user_id' => (string) $member->id], JSON_THROW_ON_ERROR);
        $auth = 'key:'.hash_hmac('sha256', $socket->id().':'.$channel->name().':'.$data, 'secret');
        $channel->subscribe($socket, $auth, $data);
        $personal = $manager->for($application)->findOrCreate('private-App.Models.User.'.$member->id);
        $personal->subscribe($socket, 'key:'.hash_hmac('sha256', $socket->id().':'.$personal->name().':'.$data, 'secret'), $data);
        $personalChannels[] = $personal;
        $sockets[] = $socket;
        $credentials[] = [$auth, $data];
    }
    [$sender, $recipient] = $fromPeer ? array_reverse($sockets) : $sockets;
    $this->peer->profile->update(['visibility' => ProfileVisibility::Hidden]);
    foreach ($personalChannels as $index => $personal) {
        $personal->broadcastToAll([
            'event' => 'message.sent',
            'data' => json_encode(['conversation_id' => $this->conversation->id, 'content' => 'allowed'], JSON_THROW_ON_ERROR),
        ]);
        expect($sockets[$index]->received)->toHaveCount(1);
        $sockets[$index]->received = [];
    }
    $typing = ['event' => 'client-typing', 'channel' => $channel->name(), 'data' => ['typing' => true]];
    ClientEvent::handle($sender, $typing);
    expect($recipient->received)->toHaveCount(1);

    if ($change === 'archive') {
        $this->conversation->update(['archived_at' => now()]);
    } else {
        [$blocker, $blocked] = $change === 'author-blocks' ? [$this->author, $this->peer] : [$this->peer, $this->author];
        app(BlockUser::class)->handle($blocker, $blocked);
    }

    foreach ($personalChannels as $personal) {
        $personal->broadcastToAll([
            'event' => 'message.sent',
            'data' => json_encode(['conversation_id' => $this->conversation->id, 'content' => 'must not arrive'], JSON_THROW_ON_ERROR),
        ]);
    }
    expect($recipient->received)->toHaveCount(1);
    ClientEvent::handle($sender, $typing);
    expect($recipient->received)->toHaveCount(1)
        ->and($sender->closed)->toBeTrue();
    $channel->broadcastToAll(['event' => 'message.sent', 'data' => 'must not arrive']);
    expect($recipient->received)->toHaveCount(1)->and($recipient->closed)->toBeTrue();
    foreach ($sockets as $index => $socket) {
        expect(fn () => $channel->subscribe($socket, ...$credentials[$index]))->toThrow(ConnectionUnauthorized::class);
    }
})->with(['author-blocks', 'peer-blocks', 'archive'])->with([false, true]);
