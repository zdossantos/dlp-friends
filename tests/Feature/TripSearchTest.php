<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('trip search matches departure or arrival while retaining audience and transport restrictions', function () {
    $viewer = User::factory()->withProfile()->create();
    $owner = User::factory()->withProfile()->create();
    $departure = Trip::factory()->for($owner, 'organizer')->create(['audience' => 'members', 'transport' => 'car', 'general_departure' => 'Bruxelles', 'destination' => 'Chessy']);
    $arrival = Trip::factory()->for($owner, 'organizer')->create(['audience' => 'members', 'transport' => 'car', 'general_departure' => 'Chessy', 'destination' => 'Bruxelles']);
    Trip::factory()->for($owner, 'organizer')->create(['audience' => 'matches', 'general_departure' => 'Bruxelles']);
    Trip::factory()->for($owner, 'organizer')->create(['audience' => 'members', 'transport' => 'train', 'general_departure' => 'Bruxelles']);
    Trip::factory()->for($owner, 'organizer')->create(['audience' => 'members', 'transport' => 'car', 'general_departure' => 'Lyon', 'destination' => 'Chessy', 'meeting_point' => 'Bruxelles secret']);
    $this->actingAs($viewer)->get('/trips?search=Bruxelles&transport=car')->assertInertia(fn ($page) => $page
        ->where('filters.search', 'Bruxelles')->has('trips.data', 2)
        ->where('trips.data.0.id', $departure->id)->where('trips.data.1.id', $arrival->id));
});

test('trip search treats wildcard characters as text', function () {
    $viewer = User::factory()->withProfile()->create();
    Trip::factory()->create(['audience' => 'members', 'general_departure' => 'Paris']);
    $this->actingAs($viewer)->get('/trips?search=%25')->assertInertia(fn ($page) => $page->has('trips.data', 0));
});

test('trip pagination preserves search filters and exposes navigation metadata', function () {
    $owner = User::factory()->withProfile()->create();
    Trip::factory()->count(21)->for($owner, 'organizer')->create(['audience' => 'members', 'transport' => 'car', 'general_departure' => 'Lille']);
    $this->actingAs(User::factory()->withProfile()->create())->get('/trips?search=Lille&transport=car&page=2')->assertInertia(fn ($page) => $page
        ->has('trips.data', 1)->where('trips.current_page', 2)->where('trips.last_page', 2)
        ->where('trips.prev_page_url', fn ($url) => str_contains($url, 'search=Lille') && str_contains($url, 'transport=car') && str_contains($url, 'page=1')));
});

test('trip summaries expose the organizer avatar without disclosing the meeting point', function () {
    $owner = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($owner, 'organizer')->create(['audience' => 'members']);
    $this->actingAs(User::factory()->withProfile()->create())->get('/trips')->assertInertia(fn ($page) => $page
        ->where('trips.data.0.organizer.avatar.id', $owner->profile->avatar_id)->missing('trips.data.0.meeting_point'));
});
