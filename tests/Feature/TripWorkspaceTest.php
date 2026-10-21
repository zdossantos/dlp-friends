<?php

use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('private trip lists and profiles are separately authorized', function () {
    $organizer = User::factory()->withProfile()->create();
    $member = User::factory()->withProfile()->create();
    $outsider = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($organizer, 'organizer')->create(['audience' => 'members']);
    TripRegistration::factory()->for($trip)->for($member, 'user')->create(['status' => 'accepted']);
    $pending = TripRegistration::factory()->for($trip)->create(['status' => 'pending']);
    $this->actingAs($outsider)->get("/trips/{$trip->id}")->assertInertia(fn ($page) => $page->missing('participants')->missing('registrations'));
    $this->get("/trips/{$trip->id}/participants")->assertForbidden();
    $this->get("/trips/{$trip->id}/requests")->assertForbidden();
    $this->get("/trips/{$trip->id}/members/{$member->id}")->assertForbidden();
    $this->get("/trips/{$trip->id}/members/{$organizer->id}")->assertOk()->assertInertia(fn ($page) => $page->where('profile.member.id', $organizer->id));
    $this->actingAs($member)->get("/trips/{$trip->id}/participants")->assertOk()->assertInertia(fn ($page) => $page->has('participants', 2)->missing('registrations'));
    $this->get("/trips/{$trip->id}/requests")->assertForbidden();
    $this->get("/trips/{$trip->id}/members/{$pending->user_id}")->assertNotFound();
    $this->actingAs($organizer)->get("/trips/{$trip->id}/requests")->assertOk()->assertInertia(fn ($page) => $page->has('registrations', 2));
});
