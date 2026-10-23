<?php

use App\Models\Avatar;
use App\Models\GroupChat;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripDepartureReport;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Avatar::created(fn (Avatar $avatar) => Storage::put($avatar->image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==')));
});

it('creates a trip with consistent controls and confirms sharing and cancellation', function () {
    $this->actingAs(User::factory()->withProfile()->create());
    $page = visit('/trips')->resize(320, 800)->assertSee('Trajets');
    $page->click('@trip-create')->fill('#general_departure', 'Gare de Lyon')->fill('#destination', 'Disneyland Paris')->fill('#meeting_point', 'Devant le guichet')
        ->assertScript("document.querySelector('#departs_at').value.startsWith(new Intl.DateTimeFormat('sv-SE', {timeZone:'Europe/Paris'}).format(new Date()))", true)
        ->click('#audience')->click('[data-slot="select-item"][data-value="members"]')->click('@trip-submit')->assertPresent('@trip-share-dialog')->click('@trip-share-cancel');
    expect(Trip::query()->count())->toBe(0);
    $page->assertValue('#meeting_point', 'Devant le guichet')->click('@trip-submit')->click('@trip-share-submit')->assertSee('Gare de Lyon')->assertSee('Devant le guichet')->assertNoJavaScriptErrors();
    $trip = Trip::query()->sole();
    $page->click('@trip-cancel')->fill('#trip-reason', 'Départ annulé')->click('@trip-action-submit')->assertPresent('@trip-danger-dialog')->click('@trip-danger-cancel');
    expect($trip->fresh()->cancelled_at)->toBeNull();
    $page->click('@trip-action-submit')->click('@trip-danger-submit')->assertSee('Trajet annulé')->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    expect($trip->fresh()->cancellation_reason)->toBe('Départ annulé');
});

it('accepts and removes a passenger with a reason and preserves available seats', function () {
    $organizer = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($organizer, 'organizer')->create(['audience' => 'members', 'car_seats' => 1]);
    GroupChat::query()->create(['trip_id' => $trip->id]);
    $registration = TripRegistration::factory()->for($trip)->create(['status' => 'pending']);
    $this->actingAs($organizer);
    $page = visit("/trips/{$trip->id}/requests")->on()->mobile()->assertSee($registration->user->profile->display_name);
    $page->click('@trip-accept-'.$registration->id)->assertSee('Acceptée');
    expect($registration->fresh()->status->value)->toBe('accepted')->and($trip->remainingPlaces())->toBe(0);
    $page->click('@trip-participants')->assertPathIs("/trips/{$trip->id}/participants")->click('@trip-remove-'.$registration->id)->fill('#trip-reason', 'Changement de programme')->click('@trip-action-submit')->click('@trip-danger-cancel');
    expect($registration->fresh()->status->value)->toBe('accepted');
    $page->click('@trip-action-submit')->click('@trip-danger-submit')->assertDontSee($registration->user->profile->display_name)->assertNoJavaScriptErrors();
    expect($registration->fresh()->reason)->toBe('Changement de programme')->and($trip->remainingPlaces())->toBe(1);
});

it('opens an organizer public profile and returns to the filtered list', function (string $locale) {
    $viewer = User::factory()->withProfile()->create(['locale' => $locale]);
    $trip = Trip::factory()->create(['audience' => 'members']);
    $this->actingAs($viewer);
    $page = visit('/trips?transport=car')->resize(320, 800)->assertPresent('@trip-transport-filter');
    $page->click('@trip-organizer')->assertSee($trip->organizer->profile->display_name)->assertPresent('@event-participant-profile')->click('@profile-close')->assertPathIs('/trips')->assertQueryStringHas('transport', 'car')->assertNoJavaScriptErrors();
    $page->forward()->assertPresent('@event-participant-profile')->back()->assertPathIs('/trips')->assertQueryStringHas('transport', 'car');
})->with(['fr', 'en']);

it('requests participation and shows only the members own state after removal', function () {
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->create(['audience' => 'members']);
    GroupChat::query()->create(['trip_id' => $trip->id]);
    $this->actingAs($member);
    $page = visit("/trips/{$trip->id}")->on()->mobile()->assertPresent('@trip-request');
    $page->click('@trip-request')->assertSee('En attente')->assertDontSee($trip->meeting_point)->assertNotPresent('@trip-participants');
    $trip->registrations()->where('user_id', $member->id)->update(['status' => 'removed', 'reason' => 'Explication personnelle']);
    $page->refresh()->assertSee('Explication personnelle')->assertDontSee($trip->organizer->profile->display_name)->assertDontSee($trip->meeting_point)->assertNoJavaScriptErrors();
});

it('confirms a late departure report without automatic sanction', function () {
    $organizer = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($organizer, 'organizer')->create(['departs_at' => now()->addHours(2)]);
    $departure = TripDeparture::factory()->for($trip)->create(['scheduled_departure_at' => $trip->departs_at, 'left_at' => now()]);
    $this->actingAs($organizer);
    $page = visit("/trips/{$trip->id}/requests")->on()->mobile()->assertPresent('@trip-report-'.$departure->id);
    $page->click('@trip-report-'.$departure->id)->fill('#trip-report-details', 'Départ sans prévenir')->click('@trip-report-send')->click('@trip-report-cancel');
    expect(TripDepartureReport::query()->count())->toBe(0);
    $page->click('@trip-report-send')->click('@trip-report-submit')->assertSee('Le signalement a été transmis')->assertNoJavaScriptErrors();
    expect(TripDepartureReport::query()->count())->toBe(1)->and($departure->user->fresh()->status->value)->toBe('active');
});

it('edits the private meeting point after acceptance while keeping departure details locked', function () {
    $organizer = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($organizer, 'organizer')->create(['first_accepted_at' => now()]);
    TripRegistration::factory()->for($trip)->create(['status' => 'accepted']);
    $this->actingAs($organizer);
    $page = visit("/trips/{$trip->id}")->resize(320, 800)->assertPresent('@trip-edit');
    $page->script("document.documentElement.classList.add('dark')");
    $page->click('@trip-edit')->assertDisabled('#general_departure')->assertDisabled('#departs_at')->fill('#meeting_point', 'Sous la grande horloge')->click('@trip-submit')->assertSee('Sous la grande horloge')->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)->assertNoJavaScriptErrors();
    expect($trip->fresh()->meeting_point)->toBe('Sous la grande horloge');
});

it('keeps full trips visible without offering a participation request', function () {
    $trip = Trip::factory()->create(['audience' => 'members', 'car_seats' => 1]);
    TripRegistration::factory()->for($trip)->create(['status' => 'accepted']);
    $this->actingAs(User::factory()->withProfile()->create());
    visit("/trips/{$trip->id}")->resize(320, 800)->assertSee('Complet')->assertNotPresent('@trip-request')->assertNotPresent('@trip-participants')->assertDontSee($trip->meeting_point)->assertNoJavaScriptErrors();
});

it('falls back to trips when closing a directly opened organizer profile', function () {
    $trip = Trip::factory()->create(['audience' => 'members']);
    $this->actingAs(User::factory()->withProfile()->create());
    visit("/trips/{$trip->id}/members/{$trip->organizer_user_id}")->resize(320, 800)->assertPresent('@event-participant-profile')->click('@profile-close')->assertPathIs('/trips')->assertPresent('@trip-transport-filter')->assertNoJavaScriptErrors();
});

it('shows cancelled pending requests and lets accepted passengers leave cancelled groups', function (string $status) {
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->create(['audience' => 'members', 'cancelled_at' => now(), 'cancellation_reason' => 'Train supprimé']);
    $trip->chat()->create();
    $registration = TripRegistration::factory()->for($trip)->for($member, 'user')->create(['status' => $status]);
    $this->actingAs($member);
    $page = visit("/trips/{$trip->id}")->on()->mobile()->assertSee('Trajet annulé')->assertSee('Train supprimé');
    if ($status === 'accepted') {
        $page->click('Quitter le trajet')->fill('#trip-reason', 'Je rentre autrement')->click('@trip-action-submit')->click('@trip-danger-submit')->assertSee('Je rentre autrement')->assertNoJavaScriptErrors();
        expect($registration->fresh()->status->value)->toBe('withdrawn');
        $this->get("/trips/{$trip->id}/chat")->assertForbidden();
    } else {
        $page->assertDontSee($trip->meeting_point)->assertNoJavaScriptErrors();
    }
})->with(['pending', 'accepted']);

it('opens the standard full bleed profile drawer from an organizer avatar', function () {
    $trip = Trip::factory()->create(['audience' => 'members']);
    $this->actingAs(User::factory()->withProfile()->create());
    visit('/trips')->resize(390, 844)
        ->assertPresent('[data-test="trip-organizer"] img')
        ->assertMissing('@trip-pagination')
        ->click('@trip-organizer')->assertPresent('@trip-profile-panel')
        ->assertMissing('@trip-profile-panel-header')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=trip-profile-panel-scroll]')).paddingLeft", '0px')
        ->click('@profile-close')->assertPathIs('/trips')->assertNoJavaScriptErrors();
});

it('searches departure and arrival without losing the transport filter', function () {
    $owner = User::factory()->withProfile()->create();
    Trip::factory()->for($owner, 'organizer')->create(['audience' => 'members', 'transport' => 'car', 'general_departure' => 'Bruxelles', 'destination' => 'Chessy']);
    Trip::factory()->for($owner, 'organizer')->create(['audience' => 'members', 'transport' => 'car', 'general_departure' => 'Lyon', 'destination' => 'Chessy']);
    $this->actingAs(User::factory()->withProfile()->create());
    visit('/trips?transport=car')->resize(390, 844)->fill('#trip-search', 'Bruxelles')->click('@trip-search-submit')
        ->assertSee('Bruxelles')->assertDontSee('Lyon')->assertQueryStringHas('transport', 'car')->assertQueryStringHas('search', 'Bruxelles')
        ->click('@trip-organizer')->click('@profile-close')->assertValue('#trip-search', 'Bruxelles')->assertNoJavaScriptErrors();
});
