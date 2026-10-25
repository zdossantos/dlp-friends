<?php

use App\Actions\CancelEvent;
use App\Actions\CancelTrip;
use App\Actions\MarkConversationRead;
use App\Actions\RemoveEventParticipant;
use App\Actions\RemoveTripParticipant;
use App\Actions\SendGroupChatMessage;
use App\Actions\SendMessage;
use App\Actions\SetGroupChatMessageReaction;
use App\Actions\SetMemberBan;
use App\Actions\SyncManageableUserRoles;
use App\Actions\WithdrawFromEvent;
use App\Actions\WithdrawFromTrip;
use App\Events\MessagesRead;
use App\Models\Event;
use App\Models\GroupChatMessage;
use App\Models\MemberMatch;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

// Run a real second connection at the exact SQL boundary under test.
function duringChatQuery(string $boundary, Closure $concurrent, Closure $operation): string
{
    if (! function_exists('pcntl_fork')) {
        test()->markTestSkipped('This concurrency test requires pcntl.');
    }
    $control = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    expect($control)->toBeArray();
    foreach ($control as $socket) {
        stream_set_timeout($socket, 15);
    }
    DB::disconnect();
    $pid = pcntl_fork();
    expect($pid)->not->toBe(-1);
    if ($pid === 0) {
        fclose($control[0]);
        DB::purge();
        if (fread($control[1], 1) !== 'S') {
            exit(1);
        }
        try {
            DB::statement('SET SESSION innodb_lock_wait_timeout=1');
            $concurrent();
            fwrite($control[1], "committed\n");
        } catch (QueryException $exception) {
            fwrite($control[1], ($exception->errorInfo[1] ?? 0)."\n");
        } catch (Throwable $exception) {
            fwrite($control[1], $exception::class.': '.$exception->getMessage()."\n");
        }
        exit(0);
    }
    fclose($control[1]);
    DB::reconnect();
    $result = '';
    DB::connection()->beforeExecuting(function (string $query) use ($boundary, $control, &$result): void {
        if ($result === '' && str_starts_with($query, $boundary)) {
            fwrite($control[0], 'S');
            $result = trim(fgets($control[0]) ?: 'no response');
        }
    });
    try {
        $operation();
    } finally {
        fclose($control[0]);
        pcntl_waitpid($pid, $status);
        DB::purge();
    }

    return $result;
}

test('a private read leaves a message inserted after its receipt boundary unread', function () {
    Notification::fake();
    Events::fake([MessagesRead::class]);
    $author = User::factory()->withProfile()->create();
    $reader = User::factory()->withProfile()->create();
    $conversation = MemberMatch::query()->create(['user_low_id' => $author->id, 'user_high_id' => $reader->id])->conversation()->create();
    $first = $conversation->messages()->create(['author_user_id' => $author->id, 'content' => 'Before receipt']);
    $notification = new NewMessageNotification($first);
    $notification->id = (string) Str::uuid();
    app(DatabaseChannel::class)->send($reader, $notification);

    $result = duringChatQuery('update `messages`',
        fn () => app(SendMessage::class)->handle($author, $conversation, 'During receipt'),
        fn () => app(MarkConversationRead::class)->handle($reader, $conversation),
    );

    expect($result)->toBe('committed');
    expect($first->fresh()->read_at)->not->toBeNull();
    expect($reader->notifications()->findOrFail($notification->id)->read_at)->not->toBeNull();
    expect($conversation->messages()->latest('id')->first()->read_at)->toBeNull();
    Events::assertDispatched(MessagesRead::class, fn ($event) => $event->lastReadMessageId === $first->id);

    app(MarkConversationRead::class)->handle($reader, $conversation);
    expect($conversation->messages()->whereNull('read_at')->count())->toBe(0);
});

test('group writes serialize with loss of member or organizer eligibility', function (string $context, string $operation, string $target) {
    Notification::fake();
    Events::fake();
    $admin = User::factory()->admin()->create();
    $organizer = User::factory()->withProfile()->create();
    $member = User::factory()->withProfile()->create();
    $group = $context === 'event'
        ? Event::factory()->for($organizer, 'organizer')->create()
        : Trip::factory()->for($organizer, 'organizer')->create(['audience' => 'members']);
    $group->registrations()->create(['user_id' => $member->id, 'status' => 'accepted']);
    $chat = $group->chat()->create();
    $message = GroupChatMessage::factory()->for($chat)->for($organizer, 'author')->create();
    $user = $target === 'member' ? $member : $organizer;
    $revoke = fn () => app(SyncManageableUserRoles::class)->handle($admin, $user, []);
    $write = $operation === 'message'
        ? fn () => app(SendGroupChatMessage::class)->handle($member, $chat, 'Concurrent message')
        : fn () => app(SetGroupChatMessageReaction::class)->handle($member, $chat, $message, true);
    $boundary = $operation === 'message' ? 'insert into `group_chat_messages`' : 'insert into `group_chat_message_reactions`';

    expect(duringChatQuery($boundary, $revoke, $write))->toBe('1205');

    $revoke();
    expect($write)->toThrow(AuthorizationException::class);
})->with(['event', 'trip'])->with(['message', 'reaction'])->with(['member', 'organizer']);

test('a group send rechecks access after a concurrent transition wins', function (string $context, string $transition) {
    Notification::fake();
    Events::fake();
    $admin = User::factory()->admin()->create();
    $organizer = User::factory()->withProfile()->create();
    $member = User::factory()->withProfile()->create();
    $group = $context === 'event'
        ? Event::factory()->for($organizer, 'organizer')->create()
        : Trip::factory()->for($organizer, 'organizer')->create(['audience' => 'members']);
    $registration = $group->registrations()->create(['user_id' => $member->id, 'status' => 'accepted']);
    $chat = $group->chat()->create();
    $revoke = function () use ($context, $transition, $admin, $organizer, $member, $group, $registration): void {
        match ($transition) {
            'role' => app(SyncManageableUserRoles::class)->handle($admin, $member, []),
            'ban' => app(SetMemberBan::class)->handle($admin, $member, true, 'Concurrency test'),
            'cancel' => $context === 'event'
                ? app(CancelEvent::class)->handle($organizer, $group)
                : app(CancelTrip::class)->handle($organizer, $group, 'Concurrency test'),
            'remove' => $context === 'event'
                ? app(RemoveEventParticipant::class)->handle($organizer, $registration)
                : app(RemoveTripParticipant::class)->handle($organizer, $registration, 'Concurrency test'),
            'withdraw' => $context === 'event'
                ? app(WithdrawFromEvent::class)->handle($member, $group)
                : app(WithdrawFromTrip::class)->handle($member, $group, 'Concurrency test'),
        };
    };

    $result = duringChatQuery('select * from `users` where `users`.`id` in', $revoke,
        fn () => expect(fn () => app(SendGroupChatMessage::class)->handle($member, $chat, 'Must be refused'))
            ->toThrow(AuthorizationException::class),
    );

    expect($result)->toBe('committed');
    expect($chat->messages()->whereNull('system_type')->count())->toBe(0);
})->with(['event', 'trip'])->with(['role', 'ban', 'cancel', 'remove', 'withdraw']);
