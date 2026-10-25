<?php

use App\Models\Avatar;
use App\Models\GroupChat;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripDepartureReport;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::fake(['photon.komoot.io/*' => Http::response(['features' => []])]);
    Storage::fake('local');
    Avatar::created(fn (Avatar $avatar) => Storage::put($avatar->image_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==')));
});

it('navigates between trip workspaces with an announced current page', function () {
    $this->actingAs(User::factory()->withProfile()->create());
    $page = visit('/trips')->resize(320, 800)
        ->assertAttribute('@trips-nav-discover', 'aria-current', 'page')
        ->click('@trips-nav-mine')->assertPathIs('/trips/mine')
        ->assertAttribute('@trips-nav-mine', 'aria-current', 'page')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    $page->back()->assertPathIs('/trips')
        ->assertAttribute('@trips-nav-discover', 'aria-current', 'page')
        ->assertNoJavaScriptErrors();
});

it('creates a trip with consistent controls and confirms sharing and cancellation', function () {
    $this->travelTo('2026-11-02 22:44:59 UTC');
    $this->actingAs(User::factory()->withProfile()->create());
    $page = visit('/trips')->resize(320, 800)->assertSee('Trajets');
    $page->script("Date.now = () => Date.parse('2026-11-02T22:44:59Z')");
    $page->click('@trip-create')->fill('#general_departure', 'Gare de Lyon')->fill('#destination', 'Disneyland Paris')->fill('#meeting_point', 'Devant le guichet');
    // Crossing 23:45 in Paris must not change the departure chosen when the form opened.
    $page->script("Date.now = () => Date.parse('2026-11-02T22:45:01Z')");
    $page->assertValue('#departs_at', '2026-11-02T23:59')
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

it('shows arrival and estimated duration with continuous accessible motion and ordered actions', function () {
    $this->travelTo('2026-11-02 08:00:00 UTC');
    $organizer = User::factory()->withProfile()->create();
    $this->actingAs($organizer);
    $page = visit('/trips')->resize(390, 844)->click('@trip-create');
    $page->fill('#general_departure', 'Paris')->fill('#destination', 'Chessy')->fill('#meeting_point', 'Sous l’horloge')
        ->fill('#departs_at', today('Europe/Paris')->addDays(2)->format('Y-m-d').'T23:00')
        ->fill('#arrives_at', today('Europe/Paris')->addDays(3)->format('Y-m-d').'T01:30')
        ->click('@trip-submit')->assertSee('Durée estimée : 2 h 30 min')->assertPresent('@trip-arrival-time')
        ->assertPresent('@trip-management')->assertPresent('@trip-chat')->assertPresent('@trip-cancel')
        ->assertScript("getComputedStyle(document.querySelector('.route-animated path')).animationIterationCount", 'infinite')
        ->assertScript("document.querySelector('[data-test=trip-chat]').getBoundingClientRect().top < document.querySelector('[data-test=trip-management]').getBoundingClientRect().top", true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)->assertNoJavaScriptErrors();
    $page->click('@trip-edit')->assertValue('#arrives_at', today('Europe/Paris')->addDays(3)->format('Y-m-d').'T01:30');
});

it('keeps route endpoints round with long place names on cards and details', function () {
    $owner = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($owner, 'organizer')->create(['audience' => 'members', 'general_departure' => 'Gare de Marne-la-Vallée — Chessy, rendez-vous principal', 'destination' => 'Paris, gare de Lyon — entrée côté place Louis-Armand']);
    $this->actingAs($owner);
    $round = "[...document.querySelectorAll('[data-test=trip-route-marker]')].length === 2 && [...document.querySelectorAll('[data-test=trip-route-marker]')].every(e => { const r=e.getBoundingClientRect(); return r.width >= 10 && Math.abs(r.width-r.height) < 0.1 && getComputedStyle(e).borderRadius === '50%'; })";
    $page = visit('/trips')->resize(320, 800)->assertSee($trip->general_departure)
        ->assertScript($round, true)->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
    $page->click('Détails du trajet')->assertPathIs('/trips/'.$trip->id)->assertScript($round, true)
        ->assertScript('document.documentElement.scrollWidth <= innerWidth', true)->assertNoJavaScriptErrors();
    $page->resize(1280, 900)->assertScript($round, true)->assertNoJavaScriptErrors();
});

it('suggests places for both endpoints using keyboard and preserves free text on failure', function () {
    $this->actingAs(User::factory()->withProfile()->create());
    $page = visit('/trips')->resize(320, 800)->click('@trip-create');
    $page->script("window.fetch = async () => new Response(JSON.stringify({suggestions: ['Gare de Lyon, Paris, France', 'Gare de Lyon Part-Dieu, Lyon, France']}), {status: 200, headers: {'Content-Type': 'application/json'}})");
    $page->fill('#general_departure', 'Gare de Lyon')->assertSee('Gare de Lyon, Paris, France')->keys('#general_departure', ['ArrowDown', 'Enter'])->assertValue('#general_departure', 'Gare de Lyon Part-Dieu, Lyon, France');
    $page->fill('#destination', 'Gare de Lyon')->click('@destination-place-0')->assertValue('#destination', 'Gare de Lyon, Paris, France');
    $page->script("window.fetch = async () => new Response('{}', {status: 503})");
    $page->fill('#destination', 'Un lieu libre')->assertSee('Recherche indisponible. Tu peux saisir ton lieu librement.')->assertValue('#destination', 'Un lieu libre')->assertScript('document.documentElement.scrollWidth <= innerWidth', true)->assertNoJavaScriptErrors();
});

it('creates a return trip by public transport without car seats', function (string $transport) {
    $this->actingAs(User::factory()->withProfile()->create());

    visit('/trips')->resize(390, 844)->click('@trip-create')
        ->click('#transport')->click('[data-slot="select-item"][data-value="'.$transport.'"]')
        ->click('#direction')->click('[data-slot="select-item"][data-value="return"]')
        ->assertMissing('#car_seats')
        ->fill('#general_departure', 'Chessy')->fill('#destination', 'Paris')
        ->fill('#meeting_point', 'Sous la grande horloge')->click('@trip-submit')
        ->assertSee('Sous la grande horloge')->assertPresent('@trip-chat')->assertNoJavaScriptErrors();

    $trip = Trip::query()->sole();
    expect($trip->transport->value)->toBe($transport)
        ->and($trip->direction->value)->toBe('return')
        ->and($trip->car_seats)->toBeNull();
})->with(['train', 'rer', 'bus']);

it('withdraws a pending request and reapplies without duplicating registrations or departures', function () {
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->create(['audience' => 'members']);
    $trip->chat()->create();
    $this->actingAs($member);

    $page = visit("/trips/{$trip->id}")->click('@trip-request')->assertSee('En attente');
    $registration = $trip->registrations()->sole();
    $page->click('Retirer ma demande')->assertPresent('@trip-request')->assertDontSee($trip->meeting_point);
    expect($registration->fresh()->status->value)->toBe('withdrawn')
        ->and($trip->departures()->count())->toBe(0);

    $page->click('@trip-request')->assertSee('En attente')->assertNoJavaScriptErrors();
    expect($trip->registrations()->sole()->id)->toBe($registration->id)
        ->and($registration->fresh()->status->value)->toBe('pending')
        ->and($registration->fresh()->reason)->toBeNull();
});

it('lets an accepted passenger cancel then confirm leaving and revokes private access', function () {
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->create(['audience' => 'members', 'car_seats' => 1]);
    $trip->chat()->create();
    $registration = TripRegistration::factory()->for($trip)->for($member, 'user')->create(['status' => 'accepted']);
    $this->actingAs($member);

    $page = visit("/trips/{$trip->id}")->assertSee($trip->meeting_point)->assertPresent('@trip-chat')
        ->click('Quitter le trajet')->fill('#trip-reason', 'Je prends un autre train')
        ->click('@trip-action-submit')->click('@trip-danger-cancel');
    expect($registration->fresh()->status->value)->toBe('accepted')->and($trip->remainingPlaces())->toBe(0);
    $page->click('@trip-action-submit')->click('@trip-danger-submit')
        ->assertMissing('@trip-chat')->assertMissing('@trip-participants')
        ->assertDontSee($trip->meeting_point)->assertNoJavaScriptErrors();
    expect($registration->fresh()->status->value)->toBe('withdrawn')
        ->and($trip->remainingPlaces())->toBe(1)
        ->and($trip->departures()->sole()->reason)->toBe('Je prends un autre train');
    $page->assertScript("async () => (await fetch('/trips/{$trip->id}/chat')).status", 403);
});

it('rejects a stale acceptance when another passenger has taken the last seat', function () {
    $organizer = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($organizer, 'organizer')->create(['audience' => 'members', 'car_seats' => 1]);
    $trip->chat()->create();
    $pending = TripRegistration::factory()->for($trip)->create(['status' => 'pending']);
    $this->actingAs($organizer);
    $page = visit("/trips/{$trip->id}/requests")->assertPresent('@trip-accept-'.$pending->id);
    TripRegistration::factory()->for($trip)->create(['status' => 'accepted']);

    $page->click('@trip-accept-'.$pending->id)->assertSee('Ce trajet est complet.')->assertNoJavaScriptErrors();
    expect($pending->fresh()->status->value)->toBe('pending')->and($trip->remainingPlaces())->toBe(0);
});

it('rejects lowering capacity below accepted passengers and allows correcting the form', function () {
    $organizer = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($organizer, 'organizer')->create(['car_seats' => 3, 'first_accepted_at' => now()]);
    TripRegistration::factory()->for($trip)->count(2)->create(['status' => 'accepted']);
    $this->actingAs($organizer);

    $page = visit("/trips/{$trip->id}")->click('@trip-edit')->fill('#car_seats', '1')->click('@trip-submit')
        ->assertSee('Le nombre de places ne peut pas être inférieur au nombre de participants acceptés.')
        ->assertValue('#car_seats', '1');
    expect($trip->fresh()->car_seats)->toBe(3);
    $page->fill('#car_seats', '2')->click('@trip-submit')->assertMissing('#car_seats')->assertNoJavaScriptErrors();
    expect($trip->fresh()->car_seats)->toBe(2)->and($trip->fresh()->remainingPlaces())->toBe(0);
});

it('keeps trip private routes inaccessible to non accepted members', function (?string $status) {
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->create(['audience' => 'members']);
    $trip->chat()->create();
    if ($status !== null) {
        TripRegistration::factory()->for($trip)->for($member, 'user')->create(['status' => $status]);
    }
    $this->actingAs($member);
    $page = visit("/trips/{$trip->id}")->assertDontSee($trip->meeting_point)->assertMissing('@trip-chat')->assertMissing('@trip-participants');
    foreach (['chat', 'participants', 'requests'] as $path) {
        $page->assertScript("async () => (await fetch('/trips/{$trip->id}/{$path}')).status", 403);
    }
    $page->assertNoJavaScriptErrors();
})->with([null, 'pending', 'refused', 'withdrawn', 'removed']);

it('notifies a candidate of acceptance and opens the meeting point and group from notifications', function () {
    $member = User::factory()->withProfile()->create();
    $trip = Trip::factory()->create(['audience' => 'members']);
    $trip->chat()->create();
    $this->actingAs($member);
    visit("/trips/{$trip->id}")->click('@trip-request')->assertSee('En attente')->assertDontSee($trip->meeting_point);
    $registration = $trip->registrations()->sole();
    $this->actingAs($trip->organizer);
    visit("/trips/{$trip->id}/requests")->click('@trip-accept-'.$registration->id)->assertSee('Acceptée');
    $notification = $member->notifications()->sole();
    $this->actingAs($member);
    visit('/notifications')->click('@notification-'.$notification->id)->assertPathIs("/trips/{$trip->id}")
        ->assertSee($trip->meeting_point)->click('@trip-chat')->assertPathIs("/trips/{$trip->id}/chat")
        ->assertPresent('#message-content')->assertNoJavaScriptErrors();
});
