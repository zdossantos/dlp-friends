<?php

use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripDepartureReport;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->travelTo('2026-11-21 12:00:00'));

test('trip retention uses the exact thirty day instant and earlier cancellation and is idempotent', function () {
    $due = Trip::factory()->create(['departs_at' => now()->subDays(30)]);
    $early = Trip::factory()->create(['departs_at' => now()->subDays(30)->addSecond()]);
    $cancelled = Trip::factory()->create(['departs_at' => now()->addDay(), 'cancelled_at' => now()->subDays(30)]);
    $lateCancellation = Trip::factory()->create(['departs_at' => now()->subDays(30), 'cancelled_at' => now()->subDays(29)]);
    $this->artisan('trips:purge-expired')->assertSuccessful();
    expect($due->fresh())->toBeNull()->and($cancelled->fresh())->toBeNull()->and($lateCancellation->fresh())->toBeNull()->and($early->fresh())->not->toBeNull();
    $this->artisan('trips:purge-expired')->assertSuccessful();
    expect(Trip::count())->toBe(1);
    $this->travel(1)->seconds();
    $this->artisan('trips:purge-expired')->assertSuccessful();
    expect(Trip::count())->toBe(0);
});

test('trip purge detaches open and closed minimal reports without retaining chat or precise meeting point', function (bool $closed) {
    $trip = Trip::factory()->create(['departs_at' => now()->subDays(30), 'meeting_point' => 'PRIVATE-MEETING']);
    $chat = $trip->chat()->create();
    $chat->messages()->create(['author_user_id' => $trip->organizer_user_id, 'content' => 'PRIVATE-CHAT']);
    $registration = TripRegistration::factory()->for($trip)->create(['status' => 'withdrawn']);
    $departure = TripDeparture::factory()->for($trip)->create(['trip_registration_id' => $registration->id, 'user_id' => $registration->user_id]);
    $report = TripDepartureReport::factory()->create(['trip_departure_id' => $departure->id, 'reporter_user_id' => $trip->organizer_user_id, 'target_user_id' => $registration->user_id, 'details' => 'Explication conservée', 'closed_at' => $closed ? now() : null, 'decision' => $closed ? 'Classé' : null]);
    $this->artisan('trips:purge-expired')->assertSuccessful();
    expect($trip->fresh())->toBeNull()->and($chat->fresh())->toBeNull()->and($registration->fresh())->toBeNull()->and($departure->fresh())->toBeNull()
        ->and($report->fresh()->trip_departure_id)->toBeNull()->and($report->fresh()->details)->toBe('Explication conservée')
        ->and(json_encode($report->fresh()->toArray()))->not->toContain('PRIVATE-');
})->with([false, true]);

test('trip purge freezes banned account data at every relationship endpoint', function (string $endpoint) {
    $trip = Trip::factory()->create(['departs_at' => now()->subDays(40)]);
    $banned = User::factory()->create(['status' => 'banned']);
    $chat = $trip->chat()->create();
    if ($endpoint === 'organizer') {
        $trip->forceFill(['organizer_user_id' => $banned->id])->save();
    } elseif ($endpoint === 'registration') {
        TripRegistration::factory()->for($trip)->for($banned)->create();
    } elseif ($endpoint === 'message') {
        $chat->messages()->create(['author_user_id' => $banned->id, 'content' => 'Gelé']);
    } elseif ($endpoint === 'reaction') {
        $chat->messages()->create(['author_user_id' => $trip->organizer_user_id, 'content' => 'Message'])->reactions()->create(['user_id' => $banned->id]);
    } elseif ($endpoint === 'read') {
        $chat->reads()->create(['user_id' => $banned->id]);
    } elseif ($endpoint === 'departure') {
        $registration = TripRegistration::factory()->for($trip)->create();
        TripDeparture::factory()->for($trip)->create(['trip_registration_id' => $registration->id, 'user_id' => $banned->id]);
    } else {
        $departure = TripDeparture::factory()->for($trip)->create();
        TripDepartureReport::factory()->create(['trip_departure_id' => $departure->id, $endpoint.'_user_id' => $banned->id]);
    }
    $this->artisan('trips:purge-expired')->assertSuccessful();
    expect($trip->fresh())->not->toBeNull()->and($chat->fresh())->not->toBeNull();
    $banned->forceFill(['status' => 'active'])->save();
    $this->artisan('trips:purge-expired')->assertSuccessful();
    expect($trip->fresh())->toBeNull();
})->with(['organizer', 'registration', 'message', 'reaction', 'read', 'departure', 'reporter', 'target']);

test('trip purge rechecks a ban committed after the batch was selected', function () {
    $trip = Trip::factory()->create(['departs_at' => now()->subDays(40)]);
    $changed = false;
    DB::listen(function ($event) use ($trip, &$changed) {
        if (! $changed && str_starts_with($event->sql, 'select') && str_contains($event->sql, '`users`') && str_contains($event->sql, 'for update')) {
            $changed = true;
            User::whereKey($trip->organizer_user_id)->update(['status' => 'banned']);
        }
    });
    $this->artisan('trips:purge-expired')->assertSuccessful();
    expect($changed)->toBeTrue()->and($trip->fresh())->not->toBeNull();
});

test('trip retention is scheduled at four in Paris with shared scheduler locks', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'trips:purge-expired'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 4 * * *')->and($event->timezone)->toBe('Europe/Paris')->and($event->withoutOverlapping)->toBeTrue()->and($event->onOneServer)->toBeTrue();
});
