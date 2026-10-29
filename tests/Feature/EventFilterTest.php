<?php

use App\Enums\EventRegistrationMode;
use App\Enums\UserStatus;
use App\Models\Block;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-01-01 12:00:00');
    $this->viewer = User::factory()->withProfile()->create();
    $this->actingAs($this->viewer);
});

test('date bounds include the whole Paris day across daylight saving changes', function (string $day, string $start, string $end) {
    Event::factory()->create(['starts_at' => date('Y-m-d H:i:s', strtotime($start) - 1)]);
    $first = Event::factory()->create(['starts_at' => $start]);
    $last = Event::factory()->create(['starts_at' => $end]);
    Event::factory()->create(['starts_at' => date('Y-m-d H:i:s', strtotime($end) + 1)]);

    $this->get(route('events.index', ['from' => $day, 'to' => $day]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('events', 2)->where('events.0.id', $first->id)->where('events.1.id', $last->id));
})->with([
    ['2026-03-29', '2026-03-28 23:00:00', '2026-03-29 21:59:59'],
    ['2026-10-25', '2026-10-24 22:00:00', '2026-10-25 22:59:59'],
]);

test('search matches title or description literally without searching private locations', function () {
    $title = Event::factory()->create(['title' => 'Café 100%_!', 'description' => 'Discussion']);
    $description = Event::factory()->create(['title' => 'Rencontre', 'description' => 'cafe 100%_! ensemble']);
    Event::factory()->create(['title' => 'Cafe 100 abc!', 'description' => 'Discussion']);
    Event::factory()->create(['title' => 'Rencontre', 'description' => 'Discussion', 'detailed_location' => 'cafe 100%_!']);

    $this->get(route('events.index', ['search' => '  cafe 100%_!  ']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters.search', 'cafe 100%_!')->has('events', 2)
        ->where('events.0.id', $title->id)->where('events.1.id', $description->id)
        ->missing('events.0.detailedLocation')->missing('events.0.participants'));
});

test('both personal lists combine text and optional date bounds including past events', function () {
    $organized = Event::factory()->for($this->viewer, 'organizer')->create(['title' => 'Retrouvailles', 'starts_at' => '2025-12-10 12:00:00', 'cancelled_at' => now()]);
    $joined = Event::factory()->create(['title' => 'Retrouvailles', 'starts_at' => '2025-12-10 15:00:00']);
    EventRegistration::factory()->accepted()->create(['event_id' => $joined->id, 'user_id' => $this->viewer->id]);
    Event::factory()->for($this->viewer, 'organizer')->create(['title' => 'Retrouvailles', 'starts_at' => '2025-12-11 12:00:00']);
    Event::factory()->for($this->viewer, 'organizer')->create(['title' => 'Autre', 'starts_at' => '2025-12-10 12:00:00']);

    foreach ([['from' => '2025-12-10', 'to' => '2025-12-10'], ['to' => '2025-12-10']] as $dates) {
        $this->get(route('events.mine', [...$dates, 'search' => 'Retrouvailles']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('organized', 1)->where('organized.0.id', $organized->id)
            ->has('participating', 1)->where('participating.0.id', $joined->id));
    }
});

test('filtered discovery keeps every visibility exclusion', function () {
    $visible = Event::factory()->create(['title' => 'Retrouvailles']);
    Event::factory()->create(['title' => 'Retrouvailles', 'starts_at' => now()->subMinute()]);
    Event::factory()->create(['title' => 'Retrouvailles', 'cancelled_at' => now()]);
    Event::factory()->create(['title' => 'Retrouvailles', 'capacity' => 1]);
    Event::factory()->for(User::factory()->withProfile()->create(['status' => UserStatus::Banned]), 'organizer')->create(['title' => 'Retrouvailles']);
    foreach ([true, false] as $outgoing) {
        $blocked = Event::factory()->create(['title' => 'Retrouvailles']);
        Block::factory()->create(['blocker_user_id' => $outgoing ? $this->viewer->id : $blocked->organizer_user_id, 'blocked_user_id' => $outgoing ? $blocked->organizer_user_id : $this->viewer->id]);
    }
    $this->get(route('events.index', ['search' => 'Retrouvailles', 'from' => '2025-01-01']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('events', 1)->where('events.0.id', $visible->id));
});

test('invalid filters are rejected on lists and direct panels', function (array $filters, string $field) {
    $event = Event::factory()->for($this->viewer, 'organizer')->create();
    foreach (['events.index', 'events.mine', 'events.show'] as $route) {
        $this->getJson(route($route, ($route === 'events.show' ? ['event' => $event] : []) + $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }
})->with([
    [['from' => '2026-02-30'], 'from'],
    [['to' => 'yesterday'], 'to'],
    [['from' => '2026-03-30', 'to' => '2026-03-29'], 'to'],
    [['search' => ['bad']], 'search'],
    [['search' => str_repeat('a', 161)], 'search'],
]);

test('direct panels preserve normalized filters and the close URL in both workspaces', function (string $origin, string $route) {
    $event = Event::factory()->for($this->viewer, 'organizer')->create(['title' => 'Rencontre']);
    $filters = ['from' => '2026-01-01', 'to' => '2026-02-01', 'search' => 'Rencontre'];
    foreach (['events.show', 'events.edit', 'events.participants.index', 'events.registrations.index'] as $panelRoute) {
        $this->get(route($panelRoute, ['event' => $event, 'origin' => $origin, ...$filters]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters', $filters)
            ->where('closeHref', route($route, $filters, false)));
    }
})->with([['discover', 'events.index'], ['mine', 'events.mine']]);

test('creating updating and cancelling preserve filters in redirects', function (string $origin) {
    $filters = ['from' => '2026-02-01', 'to' => '2026-02-28', 'search' => 'Rencontre'];
    $query = [...$filters, 'origin' => $origin];
    $attributes = [
        'title' => 'Rencontre entre amis',
        'description' => 'Une journée au parc ensemble.',
        'general_location' => 'Disneyland Park',
        'detailed_location' => 'Sous l’horloge de la gare',
        'starts_at' => '2026-02-10T10:30',
        'capacity' => 4,
        'registration_mode' => EventRegistrationMode::Automatic->value,
    ];

    $response = $this->post(route('events.store', $query), $attributes);
    $event = Event::query()->firstOrFail();
    $expected = route('events.show', [
        'event' => $event,
        ...$filters,
        ...($origin === 'mine' ? ['origin' => 'mine'] : []),
    ], false);
    $response->assertRedirect($expected);
    $this->patch(route('events.update', ['event' => $event, ...$query]), [...$attributes, 'title' => 'Rencontre modifiée'])
        ->assertRedirect($expected);
    $this->patch(route('events.cancel', ['event' => $event, ...$query]))
        ->assertRedirect($expected);
})->with(['discover', 'mine']);
