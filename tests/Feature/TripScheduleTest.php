<?php

use App\Data\TripWorkspaceData;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-10-23 08:00:00 UTC');
    $this->organizer = User::factory()->withProfile()->create();
    $this->actingAs($this->organizer);
    $this->schedule = ['transport' => 'car', 'direction' => 'outbound', 'general_departure' => 'Paris', 'destination' => 'Chessy', 'departs_at' => '2026-10-24T23:30', 'arrives_at' => '2026-10-25T03:30', 'meeting_point' => 'Devant la gare', 'car_seats' => 2];
});

test('arrival is stored in UTC and duration uses elapsed time across midnight and DST', function () {
    $this->post('/trips', $this->schedule)->assertSessionHasNoErrors();
    $trip = Trip::query()->sole();
    expect($trip->arrives_at?->format('Y-m-d H:i'))->toBe('2026-10-25 02:30');
    $data = app(TripWorkspaceData::class)->detail($this->organizer, $trip);
    expect($data['estimated_duration_minutes'])->toBe(300)
        ->and($data['arrives_at'])->toBe('2026-10-25T02:30:00+00:00');
});

test('arrival must be later than departure', function (string $arrival) {
    $this->post('/trips', [...$this->schedule, 'arrives_at' => $arrival])->assertSessionHasErrors('arrives_at');
    $this->assertDatabaseEmpty('trips');
})->with(['2026-10-24T23:29', '2026-10-24T23:30']);

test('nonexistent Paris arrival is rejected with a translated error', function () {
    $this->post('/trips', [...$this->schedule, 'departs_at' => '2027-03-28T01:30', 'arrives_at' => '2027-03-28T02:30'])
        ->assertSessionHasErrors(['arrives_at' => __('trips.validation.local_time')]);
});

test('unknown arrival remains unknown instead of inventing a duration', function () {
    $this->post('/trips', [...$this->schedule, 'arrives_at' => null])->assertSessionHasNoErrors();
    $data = app(TripWorkspaceData::class)->detail($this->organizer, Trip::query()->sole());
    expect($data['arrives_at'])->toBeNull()->and($data['estimated_duration_minutes'])->toBeNull();
});

test('arrival can be edited before acceptance but not after it', function () {
    $this->post('/trips', $this->schedule)->assertSessionHasNoErrors();
    $trip = Trip::query()->sole();
    $data = [...$this->schedule, 'arrives_at' => '2026-10-25T04:00'];
    $this->patch('/trips/'.$trip->id, $data)->assertSessionHasNoErrors();
    expect($trip->fresh()->arrives_at?->format('H:i'))->toBe('03:00');
    $trip->update(['first_accepted_at' => now()]);
    $this->patch('/trips/'.$trip->id, $this->schedule)->assertSessionHasErrors('arrives_at');
    $this->patch('/trips/'.$trip->id, [...$data, 'arrives_at' => null])->assertSessionHasErrors('arrives_at');
    $this->patch('/trips/'.$trip->id, [...$data, 'meeting_point' => 'Sous l’horloge'])->assertSessionHasNoErrors();
    expect($trip->fresh()->arrives_at?->format('H:i'))->toBe('03:00');
});
