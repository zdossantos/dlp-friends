<?php

use App\Actions\CancelTrip;
use App\Actions\DecideTripRegistration;
use App\Actions\RequestTripParticipation;
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

test('former participants can request again without disclosing private history', function (string $status) {
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->create(['audience' => 'members']);
    $trip->chat()->create();
    $registration = TripRegistration::factory()->for($trip)->for($member, 'user')->create(['status' => $status, 'reason' => 'Ancienne décision']);
    $this->actingAs($member)->get("/trips/{$trip->id}")->assertInertia(fn ($page) => $page
        ->where('trip.can_request', true)->missing('trip.meeting_point')->missing('trip.organizer'));
    $renewed = app(RequestTripParticipation::class)->handle($member, $trip);
    expect($renewed->id)->toBe($registration->id)->and($renewed->status->value)->toBe('pending')->and($renewed->reason)->toBeNull();
    app(DecideTripRegistration::class)->handle($trip->organizer, $renewed, true, null);
    $this->get("/trips/{$trip->id}")->assertInertia(fn ($page) => $page->where('trip.registration.status', 'accepted')->where('trip.meeting_point', $trip->meeting_point));
})->with(['withdrawn', 'refused', 'removed']);

test('pending requesters see cancellation explanation without private trip details', function () {
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->create(['audience' => 'members']);
    TripRegistration::factory()->for($trip)->for($member, 'user')->create(['status' => 'pending']);
    app(CancelTrip::class)->handle($trip->organizer, $trip, 'Train supprimé');
    $this->actingAs($member)->get("/trips/{$trip->id}")->assertOk()->assertInertia(fn ($page) => $page
        ->where('trip.cancellation_reason', 'Train supprimé')->where('trip.cancelled_at', $trip->fresh()->cancelled_at->toIso8601String())
        ->where('trip.can_request', false)->missing('trip.meeting_point')->missing('trip.organizer')->missing('participants'));
});

test('batched trip history preserves privacy and accepted chat access', function (string $status) {
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->create(['audience' => 'members']);
    $trip->chat()->create();
    TripRegistration::factory()->for($trip)->for($member, 'user')->create(['status' => $status, 'reason' => 'Private decision']);
    $this->actingAs($member)->get('/trips/mine')->assertOk()->assertInertia(function ($page) use ($trip, $status) {
        $page->has('trips.data', 1)->where('trips.data.0.registration.status', $status)
            ->missing('trips.data.0.meeting_point')->where('trips.data.0.can_manage', false);
        if ($status === 'accepted') {
            $page->where('trips.data.0.chat_href', "/trips/{$trip->id}/chat")->has('trips.data.0.organizer');
        } else {
            $page->missing('trips.data.0.chat_href');
            if ($status !== 'pending') {
                $page->missing('trips.data.0.organizer');
            }
        }
    });
})->with(['pending', 'accepted', 'withdrawn', 'refused', 'removed']);
