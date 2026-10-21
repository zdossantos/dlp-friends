<?php

use App\Actions\DecideTripRegistration;
use App\Actions\RequestTripParticipation;
use App\Actions\WithdrawFromTrip;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-10-21 10:00:00 UTC');
    $this->organizer = User::factory()->withProfile()->create();
    $this->member = User::factory()->withProfile()->create();
    $this->trip = Trip::factory()->for($this->organizer, 'organizer')->create(['audience' => 'members']);
    $this->payload = ['transport' => 'car', 'direction' => 'outbound', 'general_departure' => 'Paris', 'destination' => 'Disneyland Paris', 'departs_at' => '2026-10-23T12:00', 'meeting_point' => 'Entrée de la gare', 'audience' => 'members', 'car_seats' => 2];
});

test('first ever acceptance locks essential information even after the participant leaves', function ($field, $value) {
    $registration = app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    app(DecideTripRegistration::class)->handle($this->organizer, $registration, true, null);
    app(WithdrawFromTrip::class)->handle($this->member, $this->trip, 'Imprévu');
    $this->actingAs($this->organizer)->patch('/trips/'.$this->trip->id, [...$this->payload, ...($field === 'transport' ? ['car_seats' => null] : []), $field => $value])->assertSessionHasErrors($field);
})->with([['transport', 'train'], ['direction', 'return'], ['general_departure', 'Lyon'], ['destination', 'Paris'], ['departs_at', '2026-10-24T12:00']]);

test('meeting point and seats can change without taking accepted members seats', function () {
    $registration = app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    app(DecideTripRegistration::class)->handle($this->organizer, $registration, true, null);
    $this->actingAs($this->organizer)->patch('/trips/'.$this->trip->id, [...$this->payload, 'meeting_point' => 'Quai 2', 'car_seats' => 1])->assertSessionHasNoErrors();
    expect($this->trip->fresh()->meeting_point)->toBe('Quai 2');
    $other = User::factory()->withProfile()->create();
    $this->trip->registrations()->create(['user_id' => $other->id, 'status' => 'accepted']);
    $this->patch('/trips/'.$this->trip->id, [...$this->payload, 'car_seats' => 1])->assertSessionHasErrors('car_seats');
});

test('editing a private meeting point preserves the locked departure seconds', function () {
    $this->trip->update(['departs_at' => '2026-10-23 10:00:37', 'first_accepted_at' => now()]);
    $this->actingAs($this->organizer)->patch('/trips/'.$this->trip->id, [...$this->payload, 'meeting_point' => 'Quai 3'])->assertSessionHasNoErrors();
    expect($this->trip->fresh()->departs_at->format('Y-m-d H:i:s'))->toBe('2026-10-23 10:00:37');
});
