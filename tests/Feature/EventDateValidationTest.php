<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2027-01-01 09:00:00');
    $this->organizer = User::factory()->withProfile()->create();
    $this->actingAs($this->organizer);
    $this->event = Event::factory()->for($this->organizer, 'organizer')->create();
    $this->attributes = [
        'title' => 'Une journée entre amis',
        'description' => 'Retrouvons-nous au parc.',
        'general_location' => 'Disneyland Park',
        'detailed_location' => 'Devant la gare',
        'capacity' => 6,
        'registration_mode' => 'automatic',
    ];
});

test('event mutations reject invalid local dates without changing stored events', function (string $operation, bool $json, mixed $date) {
    $before = Event::query()->get()->toArray();
    $url = $operation === 'post' ? route('events.store') : route('events.update', $this->event);
    $attributes = $this->attributes + ['starts_at' => $date];
    if ($date === '__missing__') {
        unset($attributes['starts_at']);
    }

    if ($json) {
        $this->json($operation, $url, $attributes)->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    } else {
        $this->from(route('events.index'))->withHeaders(['X-Inertia' => 'true'])
            ->{$operation}($url, $attributes)->assertRedirect(route('events.index'))->assertSessionHasErrors('starts_at');
    }

    expect(Event::query()->get()->toArray())->toBe($before);
})->with(['create' => 'post', 'update' => 'patch'])
    ->with(['JSON' => true, 'Inertia' => false])
    ->with([
        'arbitrary string' => 'not-a-date',
        'impossible date' => '2027-02-30T12:30',
        'array' => [['2027-06-01T12:30']],
        'number' => 42,
        'null' => null,
        'missing' => '__missing__',
        'spring gap' => '2027-03-28T02:30',
        'exactly now' => '2027-01-01T10:00',
        'past' => '2027-01-01T09:59',
    ]);

test('event mutations preserve Paris date conversion including the repeated autumn hour', function (string $operation, string $local, string $utc) {
    $url = $operation === 'post' ? route('events.store') : route('events.update', $this->event);
    $this->{$operation}($url, $this->attributes + ['starts_at' => $local])->assertSessionHasNoErrors()->assertRedirect();
    $event = $operation === 'post' ? Event::query()->latest('id')->firstOrFail() : $this->event->refresh();

    expect($event->starts_at->utc()->format('Y-m-d H:i:s'))->toBe($utc);
})->with(['create' => 'post', 'update' => 'patch'])
    ->with([
        'next minute' => ['2027-01-01T10:01', '2027-01-01 09:01:00'],
        'winter' => ['2027-02-01T12:30', '2027-02-01 11:30:00'],
        'summer' => ['2027-06-01T12:30', '2027-06-01 10:30:00'],
        'repeated autumn hour' => ['2027-10-31T02:30', '2027-10-31 01:30:00'],
    ]);

test('nonexistent event times have a localized validation error', function (string $locale) {
    $this->organizer->update(['locale' => $locale]);
    $expected = $locale === 'fr' ? 'Cet horaire local n’existe pas à Paris.' : 'This local time does not exist in Paris.';

    $this->postJson(route('events.store'), $this->attributes + ['starts_at' => '2027-03-28T02:30'])
        ->assertUnprocessable()->assertJsonPath('errors.starts_at.0', $expected);
})->with(['fr', 'en']);
