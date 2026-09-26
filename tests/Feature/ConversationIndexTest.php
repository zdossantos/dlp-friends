<?php

use App\Enums\ProfileVisibility;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a member sees only their conversations ordered by latest activity', function () {
    $member = User::factory()->withProfile()->create();
    $olderPeer = User::factory()->withProfile()->create();
    $newerPeer = User::factory()->withProfile()->create();
    $outsider = User::factory()->withProfile()->create();

    $older = conversationBetween($member, $olderPeer);
    $newer = conversationBetween($member, $newerPeer);
    $foreign = conversationBetween($olderPeer, $outsider);

    Message::factory()->for($older)->for($olderPeer, 'author')->create([
        'content' => 'Ancien échange',
        'created_at' => now()->subHour(),
        'read_at' => now(),
    ]);
    Message::factory()->for($newer)->for($newerPeer, 'author')->create([
        'content' => 'Dernier échange',
        'created_at' => now(),
    ]);
    Message::factory()->for($foreign)->for($outsider, 'author')->create();

    $this->actingAs($member)->get('/conversations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Conversations/Index')
            ->has('conversations', 2)
            ->where('conversations.0.id', $newer->id)
            ->where('conversations.0.participant.id', $newerPeer->id)
            ->where('conversations.0.latest_message.content', 'Dernier échange')
            ->where('conversations.0.unread_count', 1)
            ->where('conversations.1.unread_count', 0)
            ->where('currentUserId', $member->id)
            ->where('conversations.1.id', $older->id));
});

test('a new conversation without messages is ordered by its creation activity', function () {
    $member = User::factory()->withProfile()->create();
    $olderPeer = User::factory()->withProfile()->create();
    $newPeer = User::factory()->withProfile()->create();

    $older = conversationBetween($member, $olderPeer);
    $older->update(['created_at' => now()->subDay()]);
    Message::factory()->for($older)->for($olderPeer, 'author')->create([
        'created_at' => now()->subHour(),
        'read_at' => now(),
    ]);

    $new = conversationBetween($member, $newPeer);
    $new->update(['created_at' => now()]);

    $this->actingAs($member)->get('/conversations')
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.id', $new->id)
            ->where('conversations.0.latest_message', null)
            ->where('conversations.0.unread_count', 0)
            ->where('conversations.0.activity_at', $new->created_at->toISOString())
            ->where('conversations.1.id', $older->id));
});

test('the latest message follows its timestamp before its identifier', function () {
    $member = User::factory()->withProfile()->create();
    $peer = User::factory()->withProfile()->create();
    $conversation = conversationBetween($member, $peer);
    $latestAt = now()->startOfSecond();

    Message::factory()->for($conversation)->for($peer, 'author')->create([
        'content' => 'Message le plus récent',
        'created_at' => $latestAt,
    ]);
    Message::factory()->for($conversation)->for($peer, 'author')->create([
        'content' => 'Identifiant supérieur mais date antérieure',
        'created_at' => $latestAt->copy()->subMinute(),
    ]);

    $this->actingAs($member)->get('/conversations')
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.latest_message.content', 'Message le plus récent')
            ->where('conversations.0.activity_at', $latestAt->toISOString()));
});

test('messages sent by the member never count as unread', function () {
    $member = User::factory()->withProfile()->create();
    $peer = User::factory()->withProfile()->create();
    $conversation = conversationBetween($member, $peer);

    Message::factory()->for($conversation)->for($member, 'author')->create([
        'content' => 'Message sortant',
    ]);

    $this->actingAs($member)->get('/conversations')
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.unread_count', 0)
            ->where('conversations.0.latest_message.author_user_id', $member->id));
});

test('the conversation list exposes an empty collection', function () {
    $member = User::factory()->withProfile()->create();

    $this->actingAs($member)->get('/conversations')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Conversations/Index')
            ->where('conversations', []));
});

test('a conversation is excluded while the other member profile is hidden and returns when visible', function () {
    $member = User::factory()->withProfile()->create();
    $hiddenPeer = User::factory()->withProfile()->create();
    $visiblePeer = User::factory()->withProfile()->create();
    $hiddenConversation = conversationBetween($member, $hiddenPeer);
    $visibleConversation = conversationBetween($member, $visiblePeer);

    $member->profile->update(['visibility' => ProfileVisibility::Hidden]);
    $hiddenPeer->profile->update(['visibility' => ProfileVisibility::Hidden]);
    Message::factory()->for($hiddenConversation)->for($hiddenPeer, 'author')->create([
        'created_at' => now(),
    ]);
    Message::factory()->for($visibleConversation)->for($visiblePeer, 'author')->create([
        'created_at' => now()->subMinute(),
    ]);

    $this->actingAs($member)->get('/conversations')
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversations', 1)
            ->where('conversations.0.id', $visibleConversation->id)
            ->where('conversations.0.unread_count', 1));

    $hiddenPeer->profile->update(['visibility' => ProfileVisibility::Visible]);

    $this->actingAs($member)->get('/conversations')
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversations', 2)
            ->where('conversations.0.id', $hiddenConversation->id)
            ->where('conversations.0.unread_count', 1));

    $hiddenPeer->profile->update(['visibility' => ProfileVisibility::Hidden]);

    expect(Gate::forUser($member)->allows('view', $hiddenConversation))->toBeTrue()
        ->and(Gate::forUser($member)->allows('send', $hiddenConversation))->toBeTrue();
    $this->actingAs($member)->get("/conversations/{$hiddenConversation->id}")->assertOk();
});

test('a conversation is excluded when either member blocks the other', function (string $direction) {
    $member = User::factory()->withProfile()->create();
    $peer = User::factory()->withProfile()->create();
    $conversation = conversationBetween($member, $peer);
    [$blocker, $blocked] = $direction === 'incoming' ? [$peer, $member] : [$member, $peer];

    Block::factory()->create([
        'blocker_user_id' => $blocker->id,
        'blocked_user_id' => $blocked->id,
    ]);

    $this->actingAs($member)->get('/conversations')
        ->assertInertia(fn (Assert $page) => $page->where('conversations', []));
})->with(['incoming', 'outgoing']);

function conversationBetween(User $first, User $second): Conversation
{
    [$lowId, $highId] = collect([$first->id, $second->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create([
        'user_low_id' => $lowId,
        'user_high_id' => $highId,
    ]);

    return $match->conversation()->create();
}
