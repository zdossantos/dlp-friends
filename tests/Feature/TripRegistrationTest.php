<?php

use App\Actions\CancelTrip;
use App\Actions\DecideTripRegistration;
use App\Actions\RemoveTripParticipant;
use App\Actions\RequestTripParticipation;
use App\Actions\WithdrawFromTrip;
use App\Enums\TripRegistrationStatus;
use App\Models\Block;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-10-21 10:00:00 UTC');
    $this->organizer = User::factory()->withProfile()->create();
    $this->member = User::factory()->withProfile()->create();
    $this->trip = Trip::factory()->for($this->organizer, 'organizer')->create(['audience' => 'members', 'car_seats' => 1]);
});

test('requests and manual decisions reserve passenger seats and are idempotent', function () {
    $registration = app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    expect($registration->status)->toBe(TripRegistrationStatus::Pending)->and($this->trip->remainingPlaces())->toBe(1);
    expect(app(RequestTripParticipation::class)->handle($this->member, $this->trip)->id)->toBe($registration->id);
    app(DecideTripRegistration::class)->handle($this->organizer, $registration, true, null);
    app(DecideTripRegistration::class)->handle($this->organizer, $registration, true, null);
    expect($this->trip->remainingPlaces())->toBe(0)->and($this->trip->fresh()->first_accepted_at)->not->toBeNull();
    expect(Gate::forUser($this->member)->allows('viewPrivate', $this->trip))->toBeTrue();
    $this->assertDatabaseCount('trip_registrations', 1);
});

test('full trips remain visible and retain pending requests without allowing overbooking', function () {
    $other = User::factory()->withProfile()->create();
    $first = app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    $second = app(RequestTripParticipation::class)->handle($other, $this->trip);
    app(DecideTripRegistration::class)->handle($this->organizer, $first, true, null);
    expect(fn () => app(DecideTripRegistration::class)->handle($this->organizer, $second, true, null))->toThrow(ValidationException::class);
    expect($second->fresh()->status)->toBe(TripRegistrationStatus::Pending);
    $this->actingAs($other)->get('/trips')->assertOk();
    expect(fn () => app(RequestTripParticipation::class)->handle(User::factory()->withProfile()->create(), $this->trip))->toThrow(AuthorizationException::class);
});

test('accepted departure needs a reason frees capacity and preserves proof across a new request', function () {
    $registration = app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    app(DecideTripRegistration::class)->handle($this->organizer, $registration, true, null);
    expect(fn () => app(WithdrawFromTrip::class)->handle($this->member, $this->trip, null))->toThrow(ValidationException::class);
    app(WithdrawFromTrip::class)->handle($this->member, $this->trip, 'Imprévu familial');
    app(WithdrawFromTrip::class)->handle($this->member, $this->trip, 'Nouvel envoi');
    $departure = TripDeparture::query()->sole();
    expect($this->trip->remainingPlaces())->toBe(1)->and($departure->reason)->toBe('Imprévu familial');
    expect(Gate::forUser($this->member)->allows('viewPrivate', $this->trip))->toBeFalse();
    app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    expect($registration->fresh()->status)->toBe(TripRegistrationStatus::Pending);
    expect(TripDeparture::query()->sole()->id)->toBe($departure->id);
});

test('withdrawing a pending request needs no reason and creates no late departure', function () {
    app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    app(WithdrawFromTrip::class)->handle($this->member, $this->trip, null);
    $this->assertDatabaseEmpty('trip_departures');
    expect($this->trip->registrations()->sole()->status)->toBe(TripRegistrationStatus::Withdrawn);
});

test('refusal and exclusion require reasons and exclusion needs explicit confirmation', function () {
    $registration = app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    $this->actingAs($this->organizer)->patch('/trip-registrations/'.$registration->id, ['accept' => false])->assertSessionHasErrors('reason');
    $this->patch('/trip-registrations/'.$registration->id, ['accept' => false, 'reason' => 'Le groupe a changé'])->assertSessionHasNoErrors();
    expect($registration->fresh()->status)->toBe(TripRegistrationStatus::Refused);
    app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    app(DecideTripRegistration::class)->handle($this->organizer, $registration, true, null);
    $this->delete('/trip-registrations/'.$registration->id, ['reason' => 'Groupe incompatible'])->assertSessionHasErrors('confirmed');
    expect($registration->fresh()->status)->toBe(TripRegistrationStatus::Accepted);
    $this->delete('/trip-registrations/'.$registration->id, ['reason' => 'Groupe incompatible', 'confirmed' => true])->assertSessionHasNoErrors();
    expect($registration->fresh()->status)->toBe(TripRegistrationStatus::Removed)->and($this->trip->remainingPlaces())->toBe(1);
    $this->assertDatabaseEmpty('trip_departures');
});

test('only the organizer may decide exclude or cancel and cannot leave the group', function () {
    $registration = app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    $outsider = User::factory()->withProfile()->create();
    expect(fn () => app(DecideTripRegistration::class)->handle($outsider, $registration, true, null))->toThrow(AuthorizationException::class);
    expect(fn () => app(RemoveTripParticipant::class)->handle($outsider, $registration, 'Motif'))->toThrow(AuthorizationException::class);
    expect(fn () => app(CancelTrip::class)->handle($outsider, $this->trip, 'Motif'))->toThrow(AuthorizationException::class);
    expect(fn () => app(WithdrawFromTrip::class)->handle($this->organizer, $this->trip, 'Motif'))->toThrow(AuthorizationException::class);
});

test('cancellation requires a reason and confirmation without changing pending or accepted history', function () {
    $registration = app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    $this->actingAs($this->organizer)->patch('/trips/'.$this->trip->id.'/cancel', ['confirmed' => true])->assertSessionHasErrors('reason');
    $this->patch('/trips/'.$this->trip->id.'/cancel', ['reason' => 'Plus disponible'])->assertSessionHasErrors('confirmed');
    expect($this->trip->fresh()->cancelled_at)->toBeNull();
    $this->patch('/trips/'.$this->trip->id.'/cancel', ['confirmed' => true, 'reason' => 'Plus disponible'])->assertSessionHasNoErrors();
    $cancelledAt = $this->trip->fresh()->cancelled_at;
    app(CancelTrip::class)->handle($this->organizer, $this->trip, 'Deuxième envoi');
    expect($this->trip->fresh()->cancelled_at->equalTo($cancelledAt))->toBeTrue();
    expect($registration->fresh()->status)->toBe(TripRegistrationStatus::Pending);
    expect(fn () => app(DecideTripRegistration::class)->handle($this->organizer, $registration, true, null))->toThrow(ValidationException::class);
});

test('public transport caps the group at fifty including the organizer', function () {
    $this->trip->update(['transport' => 'train', 'car_seats' => null]);
    foreach (User::factory()->withProfile()->count(49)->create() as $passenger) {
        $this->trip->registrations()->create(['user_id' => $passenger->id, 'status' => TripRegistrationStatus::Accepted]);
    }
    expect($this->trip->remainingPlaces())->toBe(0);
    expect(fn () => app(RequestTripParticipation::class)->handle($this->member, $this->trip))->toThrow(AuthorizationException::class);
});

test('leaving after departure is possible but requests and decisions have ended', function () {
    $registration = app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    $other = User::factory()->withProfile()->create();
    $pending = app(RequestTripParticipation::class)->handle($other, $this->trip);
    app(DecideTripRegistration::class)->handle($this->organizer, $registration, true, null);
    $this->travelTo($this->trip->departs_at);
    expect(fn () => app(DecideTripRegistration::class)->handle($this->organizer, $pending, true, null))->toThrow(ValidationException::class);
    app(WithdrawFromTrip::class)->handle($this->member, $this->trip, 'Je quitte le groupe');
    expect($registration->fresh()->status)->toBe(TripRegistrationStatus::Withdrawn);
});

test('acceptance checks fresh blocks accounts and audience even with stale loaded objects', function () {
    $registration = app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    $this->trip->load('organizer');
    Block::query()->create(['blocker_user_id' => $this->organizer->id, 'blocked_user_id' => $this->member->id]);
    expect(fn () => app(DecideTripRegistration::class)->handle($this->organizer, $registration, true, null))->toThrow(AuthorizationException::class);
    expect($registration->fresh()->status)->toBe(TripRegistrationStatus::Pending);
});

test('an existing block with an accepted passenger prevents joining their group', function () {
    $passenger = User::factory()->withProfile()->create();
    $this->trip->update(['car_seats' => 2]);
    $this->trip->registrations()->create(['user_id' => $passenger->id, 'status' => TripRegistrationStatus::Accepted]);
    Block::query()->create(['blocker_user_id' => $passenger->id, 'blocked_user_id' => $this->member->id]);
    expect(fn () => app(RequestTripParticipation::class)->handle($this->member, $this->trip))->toThrow(AuthorizationException::class);
});

test('conditional validation errors have translations rather than translation keys', function ($locale, $field, $data) {
    $this->organizer->forceFill(['locale' => $locale])->save();
    $payload = ['transport' => 'car', 'direction' => 'outbound', 'general_departure' => 'Paris', 'destination' => 'Disneyland Paris', 'departs_at' => '2026-10-23T12:00', 'meeting_point' => 'Gare'];
    $this->actingAs($this->organizer)->post('/trips', [...$payload, ...$data])->assertSessionHasErrors($field);
    expect(session('errors')->get($field)[0])->not->toStartWith('validation.');
})->with([
    ['fr', 'car_seats', []], ['en', 'car_seats', []],
    ['fr', 'car_seats', ['transport' => 'train', 'car_seats' => 1]],
    ['en', 'car_seats', ['transport' => 'train', 'car_seats' => 1]],
]);
