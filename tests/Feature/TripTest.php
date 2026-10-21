<?php

use App\Actions\CreateTrip;
use App\Data\TripWorkspaceData;
use App\Enums\ProfileVisibility;
use App\Enums\TripAudience;
use App\Enums\TripRegistrationStatus;
use App\Enums\UserStatus;
use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-10-21 10:00:00 UTC');
    $this->organizer = User::factory()->withProfile()->create();
    $this->tripData = [
        'transport' => 'car', 'direction' => 'outbound',
        'general_departure' => 'Paris', 'destination' => 'Disneyland Paris',
        'departs_at' => '2026-10-23T09:00', 'meeting_point' => 'Sortie 3, place du marché',
        'car_seats' => 2,
    ];
});

test('creates_match_only_trip', function () {
    $this->actingAs($this->organizer)->post('/trips', $this->tripData)->assertSessionHasNoErrors()->assertRedirect();
    $trip = Trip::query()->sole();
    expect($trip->audience)->toBe(TripAudience::Matches)
        ->and($trip->organizer_user_id)->toBe($this->organizer->id)
        ->and($trip->departs_at->format('Y-m-d H:i'))->toBe('2026-10-23 07:00')
        ->and($this->organizer->fresh()->visits_visibility->value)->toBe('hidden');
    $this->assertDatabaseEmpty('events');
    $this->assertDatabaseEmpty('visits');
});

test('requires_confirmation_for_every_expansion', function () {
    $this->actingAs($this->organizer)->post('/trips', [...$this->tripData, 'audience' => 'members'])
        ->assertSessionHasErrors('confirm_members');
    $this->assertDatabaseEmpty('trips');
    $trip = app(CreateTrip::class)->handle($this->organizer, $this->tripData);
    $this->patch('/trips/'.$trip->id, [...$this->tripData, 'audience' => 'members'])->assertSessionHasErrors('confirm_members');
    $this->patch('/trips/'.$trip->id, [...$this->tripData, 'audience' => 'members', 'confirm_members' => true])->assertSessionHasNoErrors();
    $this->patch('/trips/'.$trip->id, [...$this->tripData, 'audience' => 'matches'])->assertSessionHasNoErrors();
    $this->patch('/trips/'.$trip->id, [...$this->tripData, 'audience' => 'members'])->assertSessionHasErrors('confirm_members');
    expect($trip->fresh()->audience)->toBe(TripAudience::Matches);
});

test('pending_member_receives_no_private_props', function () {
    $viewer = User::factory()->withProfile()->create();
    $trip = app(CreateTrip::class)->handle($this->organizer, [...$this->tripData, 'audience' => 'members', 'confirm_members' => true]);
    $trip->registrations()->create(['user_id' => $viewer->id, 'status' => TripRegistrationStatus::Pending]);
    $this->actingAs($viewer)->get('/trips/'.$trip->id)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Trips/Show', false)->missing('trip.meeting_point')->missing('trip.participants')->missing('messages')
        ->where('trip.registration.status', 'pending'));
    $this->actingAs($viewer)->patch('/trips/'.$trip->id, $this->tripData)->assertForbidden();
});

test('accepted_member_keeps_access_after_audience_reduction', function () {
    $viewer = User::factory()->withProfile()->create();
    $third = User::factory()->withProfile()->create();
    $trip = app(CreateTrip::class)->handle($this->organizer, $this->tripData);
    $trip->registrations()->create(['user_id' => $viewer->id, 'status' => TripRegistrationStatus::Accepted]);
    $data = app(TripWorkspaceData::class)->detail($viewer, $trip);
    expect($data['meeting_point'])->toBe('Sortie 3, place du marché');
    $this->actingAs($third)->get('/trips/'.$trip->id)->assertForbidden();
    $this->actingAs($viewer)->get('/trips/'.$trip->id)->assertOk();
});

test('validates_paris_calendar_boundary_and_dst', function ($date, $valid) {
    $response = $this->actingAs($this->organizer)->post('/trips', [...$this->tripData, 'departs_at' => $date]);
    if ($valid) {
        $this->assertDatabaseCount('trips', 1);
    } else {
        $response->assertSessionHasErrors('departs_at');
        $this->assertDatabaseEmpty('trips');
    }
})->with([
    ['2026-10-21T11:59', false], ['2026-10-21T12:00', false], ['2026-10-21T12:01', true],
    ['2027-10-21T23:59', true], ['2027-10-22T00:00', false],
    ['2027-03-28T02:30', false], ['2027-03-28T03:30', true], ['invalid', false],
]);

test('hidden organizers leave discovery but accepted members keep private access', function () {
    $viewer = User::factory()->withProfile()->create();
    $trip = app(CreateTrip::class)->handle($this->organizer, [...$this->tripData, 'audience' => 'members', 'confirm_members' => true]);
    $this->organizer->profile->update(['visibility' => ProfileVisibility::Hidden]);
    expect(Gate::forUser($viewer)->allows('view', $trip->fresh()))->toBeFalse();
    $trip->registrations()->create(['user_id' => $viewer->id, 'status' => TripRegistrationStatus::Accepted]);
    expect(Gate::forUser($viewer)->allows('viewPrivate', $trip->fresh()))->toBeTrue();
    $this->organizer->forceFill(['status' => UserStatus::Banned])->save();
    expect(Gate::forUser($viewer)->allows('viewPrivate', $trip->fresh()))->toBeFalse();
});

test('matching and blocks control public trip visibility in either direction', function () {
    $viewer = User::factory()->withProfile()->create();
    $trip = app(CreateTrip::class)->handle($this->organizer, $this->tripData);
    expect(Trip::query()->visibleTo($viewer)->count())->toBe(0);
    MemberMatch::query()->create(['user_low_id' => min($viewer->id, $this->organizer->id), 'user_high_id' => max($viewer->id, $this->organizer->id)]);
    expect(Trip::query()->visibleTo($viewer)->count())->toBe(1);
    Block::query()->create(['blocker_user_id' => $viewer->id, 'blocked_user_id' => $this->organizer->id]);
    expect(Trip::query()->visibleTo($viewer)->count())->toBe(0);
    expect(Gate::forUser($viewer)->allows('viewPrivate', $trip))->toBeFalse();
});

test('transport capacity validates car seats and rejects them for public transport', function ($transport, $seats, $valid) {
    $response = $this->actingAs($this->organizer)->post('/trips', [...$this->tripData, 'transport' => $transport, 'car_seats' => $seats]);
    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('car_seats');
})->with([['car', 0, false], ['car', 1, true], ['car', null, false], ['train', null, true], ['rer', 2, false], ['bus', null, true]]);

test('former participants retain only their own decision when audience is unavailable', function () {
    $viewer = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($this->organizer, 'organizer')->create();
    $trip->registrations()->create(['user_id' => $viewer->id, 'status' => TripRegistrationStatus::Removed, 'reason' => 'Changement de groupe']);
    $this->actingAs($viewer)->get('/trips/'.$trip->id)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Trips/Show', false)->where('trip.registration.status', 'removed')
        ->where('trip.registration.reason', 'Changement de groupe')
        ->missing('trip.meeting_point')->missing('trip.organizer')->missing('trip.participants')->missing('trip.general_departure'));
    $this->get('/trips/mine')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Trips/Mine', false)->has('trips.data', 1));
});

test('public listings never include accepted members private meeting points', function () {
    $viewer = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($this->organizer, 'organizer')->create(['audience' => 'members']);
    $trip->registrations()->create(['user_id' => $viewer->id, 'status' => TripRegistrationStatus::Accepted]);
    $this->actingAs($viewer)->get('/trips')->assertInertia(fn (Assert $page) => $page->has('trips.data', 1)->missing('trips.data.0.meeting_point'));
});

test('direct trip creation cannot bypass sharing confirmation', function () {
    expect(fn () => app(CreateTrip::class)->handle($this->organizer, [...$this->tripData, 'audience' => 'members']))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseEmpty('trips');
});

test('trip validation errors use the members locale', function ($locale, $message) {
    $this->organizer->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->organizer)->post('/trips', [...$this->tripData, 'departs_at' => '2027-03-28T02:30'])
        ->assertSessionHasErrors(['departs_at' => $message]);
})->with([
    ['fr', 'Cet horaire local n’existe pas à Paris.'],
    ['en', 'This local time does not exist in Paris.'],
]);
