<?php

use App\Events\PresenceChanged;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('an active member heartbeat records temporary presence without exposing precise activity', function () {
    $member = User::factory()->withProfile()->create();
    Event::fake([PresenceChanged::class]);

    $this->actingAs($member)->post('/presence/heartbeat')->assertNoContent();

    expect(Cache::has("presence:user:{$member->id}"))->toBeTrue()
        ->and($member->fresh()->last_active_at)->not->toBeNull();
    Event::assertDispatched(PresenceChanged::class, fn ($event) => $event->member->is($member) && $event->online);
});

test('a member can hide presence and the heartbeat clears temporary presence', function () {
    $member = User::factory()->withProfile()->create();
    Cache::put("presence:user:{$member->id}", true, now()->addMinute());

    $this->actingAs($member)->patch('/settings/account', [
        'email' => $member->email,
        'show_presence' => false,
    ])->assertRedirect('/settings/account');

    $this->actingAs($member)->post('/presence/heartbeat')->assertNoContent();

    expect($member->fresh()->show_presence)->toBeFalse()
        ->and(Cache::has("presence:user:{$member->id}"))->toBeFalse();
});

test('conversation payload exposes online state only when the participant allows it', function () {
    $member = User::factory()->withProfile()->create();
    $peer = User::factory()->withProfile()->create(['last_active_at' => now()->subMinutes(12)]);
    [$lowId, $highId] = collect([$member->id, $peer->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create(['user_low_id' => $lowId, 'user_high_id' => $highId]);
    $conversation = $match->conversation()->create();
    Cache::put("presence:user:{$peer->id}", true, now()->addMinute());

    $this->actingAs($member)->get("/conversations/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('participant.presence.online', true)
            ->where('participant.presence.last_active_at', fn ($value) => is_string($value)));

    $peer->update(['show_presence' => false]);

    $this->actingAs($member)->get("/conversations/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page->where('participant.presence', null));
});
