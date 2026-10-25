<?php

use App\Models\Event;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('notification lists refresh event and trip chat access after a block', function () {
    $viewer = User::factory()->withProfile()->create();
    $owner = User::factory()->withProfile()->create();
    $event = Event::factory()->for($owner, 'organizer')->create();
    $event->registrations()->create(['user_id' => $viewer->id, 'status' => 'accepted']);
    $event->chat()->create();
    $trip = Trip::factory()->for($owner, 'organizer')->create(['audience' => 'members']);
    TripRegistration::factory()->for($trip)->for($viewer)->create(['status' => 'accepted']);
    $trip->chat()->create();
    $targets = ['event' => $event->id, 'event_chat' => $event->id, 'trip' => $trip->id, 'trip_chat' => $trip->id];
    foreach ($targets as $type => $id) {
        $viewer->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => [
            'category' => 'events', 'translation_key' => 'notifications.items.event_changed',
            'target_type' => $type, 'target_id' => $id,
        ]]);
    }
    $expected = ["/events/{$event->id}", "/events/{$event->id}/chat", "/trips/{$trip->id}", "/trips/{$trip->id}/chat"];
    $this->actingAs($viewer)->get('/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('notifications.data', 4)
        ->where('notifications.data', fn ($rows) => collect($rows)->pluck('target_url')->sort()->values()->all() === collect($expected)->sort()->values()->all()));

    $viewer->blocksCreated()->create(['blocked_user_id' => $owner->id]);
    // Event chat membership survives a block; trip chat access does not.
    $expected = ['/notifications', "/events/{$event->id}/chat", "/trips/{$trip->id}", '/notifications'];
    $this->get('/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('notifications.data', fn ($rows) => collect($rows)->pluck('target_url')->sort()->values()->all() === collect($expected)->sort()->values()->all()));
});
