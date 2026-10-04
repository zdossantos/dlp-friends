<?php

use App\Events\MessageReactionUpdated;
use App\Events\MessagesRead;
use App\Models\MemberMatch;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

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
