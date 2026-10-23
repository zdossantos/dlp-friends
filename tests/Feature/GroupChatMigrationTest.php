<?php

use App\Data\GroupChatData;
use App\Models\Event;
use App\Models\GroupChat;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

test('generalizing event chats preserves message reaction and read identities', function () {
    $migration = require database_path('migrations/2026_10_21_110000_generalize_event_chats_to_group_chats.php');
    $migration->down();
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $event = Event::factory()->for($owner, 'organizer')->create();
    $chatId = DB::table('event_chats')->insertGetId(['event_id' => $event->id]);
    $first = DB::table('event_chat_messages')->insertGetId(['event_chat_id' => $chatId, 'author_user_id' => $owner->id, 'content' => 'Conservé']);
    $last = DB::table('event_chat_messages')->insertGetId(['event_chat_id' => $chatId, 'author_user_id' => $owner->id, 'content' => 'Non lu']);
    $reactionId = DB::table('event_chat_message_reactions')->insertGetId(['event_chat_message_id' => $first, 'user_id' => $member->id]);
    $readId = DB::table('event_chat_reads')->insertGetId(['event_chat_id' => $chatId, 'user_id' => $member->id, 'last_read_message_id' => $first]);
    $migration->up();
    $group = GroupChat::query()->findOrFail($chatId);
    expect($group->messages()->pluck('id')->all())->toBe([$first, $last]);
    expect($group->messages()->first()->content)->toBe('Conservé');
    expect(GroupChatData::unreadCount($group, $member))->toBe(1);
    expect(DB::table('group_chat_message_reactions')->where('id', $reactionId)->value('group_chat_message_id'))->toBe($first);
    expect($group->reads()->sole()->id)->toBe($readId);
});

test('database requires exactly one event or trip context', function (bool $both) {
    $data = $both ? ['event_id' => Event::factory()->create()->id, 'trip_id' => Trip::factory()->create()->id] : [];
    expect(fn () => GroupChat::query()->create($data))->toThrow(QueryException::class);
})->with([false, true]);
