<?php

use App\Events\EventChatAccessChanged;
use App\Events\EventChatMessageReactionUpdated;
use App\Events\EventChatMessageSent;
use App\Events\MatchCreated;
use App\Events\MessageReactionUpdated;
use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Events\PresenceChanged;
use App\Models\Event;
use App\Models\MemberMatch;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('drops the real queued broadcast before delivery when its author is banned', function (string $kind) {
    $author = User::factory()->withProfile()->create();
    $recipient = User::factory()->withProfile()->create();
    $match = MemberMatch::query()->create(['user_low_id' => $author->id, 'user_high_id' => $recipient->id]);
    $conversation = $match->conversation()->create();
    $message = $conversation->messages()->create(['author_user_id' => $author->id, 'content' => 'Queued content']);
    $event = Event::factory()->create(['organizer_user_id' => $recipient->id]);
    $chat = $event->chat()->create();
    $groupMessage = $chat->messages()->create(['author_user_id' => $author->id, 'content' => 'Queued group content']);
    $broadcast = match ($kind) {
        'message' => new MessageSent($message),
        'read' => new MessagesRead($conversation->id, $author->id, $message->id, now()->toISOString()),
        'reaction' => new MessageReactionUpdated($conversation->id, $message->id, $author->id, 1, true),
        'group-message' => new EventChatMessageSent($groupMessage),
        'group-reaction' => new EventChatMessageReactionUpdated($chat->id, $groupMessage->id, $author->id, 1, true),
        'group-access' => new EventChatAccessChanged($event->id, $author->id, 'accepted'),
        'match' => new MatchCreated($match, $recipient),
        'presence' => new PresenceChanged($author, true),
    };
    expect($broadcast->broadcastWhen())->toBeTrue();
    $job = unserialize(serialize(new BroadcastEvent($broadcast)));
    $author->forceFill(['status' => 'banned'])->save();
    $manager = Mockery::mock(Factory::class);
    $manager->shouldNotReceive('connection');
    $job->handle($manager);
})->with(['message', 'read', 'reaction', 'group-message', 'group-reaction', 'group-access', 'match', 'presence']);

it('does not deliver a queued private notification after either participant is banned', function (bool $recipientBanned) {
    $author = User::factory()->withProfile()->create();
    $recipient = User::factory()->withProfile()->create();
    $conversation = MemberMatch::query()->create(['user_low_id' => $author->id, 'user_high_id' => $recipient->id])->conversation()->create();
    $message = $conversation->messages()->create(['author_user_id' => $author->id, 'content' => 'Queued private content']);
    $notification = new NewMessageNotification($message);
    ($recipientBanned ? $recipient : $author)->forceFill(['status' => 'banned'])->save();
    (new SendQueuedNotifications($recipient, $notification, ['database']))->handle(app(ChannelManager::class));
    $this->assertDatabaseCount('notifications', 0);
})->with([false, true]);

it('refuses fresh writes and drops queued read and reaction broadcasts after a ban', function () {
    Queue::fake();
    $author = User::factory()->withProfile()->create();
    $recipient = User::factory()->withProfile()->create();
    $conversation = MemberMatch::query()->create(['user_low_id' => $author->id, 'user_high_id' => $recipient->id])->conversation()->create();
    $message = $conversation->messages()->create(['author_user_id' => $author->id, 'content' => 'Before ban']);
    $recipient->forceFill(['status' => 'banned'])->save();
    $this->actingAs($author)->post("/conversations/{$conversation->id}/messages", ['content' => 'After'])->assertForbidden();
    $this->assertDatabaseCount('messages', 1);
    expect((new MessagesRead($conversation->id, $recipient->id, $message->id, now()->toISOString()))->broadcastWhen())->toBeFalse();
    expect((new MessageReactionUpdated($conversation->id, $message->id, $recipient->id, 1, true))->broadcastWhen())->toBeFalse();
});
