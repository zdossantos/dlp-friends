<?php

use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-10-20 12:00:00');
});

test('members manage entire inclusive stays without creating a group', function () {
    $member = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create();
    $this->actingAs($member)->post('/visits', [
        'starts_on' => '2026-10-23', 'ends_on' => '2026-10-25', 'user_id' => $other->id,
    ])->assertRedirect('/visits');
    $visit = Visit::query()->sole();
    expect($visit->user_id)->toBe($member->id);
    expect($visit->starts_on->toDateString())->toBe('2026-10-23');
    expect($visit->ends_on->toDateString())->toBe('2026-10-25');
    $this->assertDatabaseEmpty('events');
    $this->assertDatabaseEmpty('event_chats');
    $this->patch('/visits/'.$visit->id, ['starts_on' => '2026-12-30', 'ends_on' => '2027-01-02'])->assertRedirect('/visits');
    expect($visit->fresh()->ends_on->toDateString())->toBe('2027-01-02');
    $this->actingAs($other)->patch('/visits/'.$visit->id, ['starts_on' => '2026-10-23', 'ends_on' => '2026-10-25'])->assertForbidden();
    $this->delete('/visits/'.$visit->id)->assertForbidden();
    $this->actingAs($member)->delete('/visits/'.$visit->id)->assertRedirect('/visits');
    $this->assertDatabaseEmpty('visits');
});

test('stay boundaries are validated in Paris calendar days', function ($start, $end, $valid) {
    $member = User::factory()->withProfile()->create();
    $response = $this->actingAs($member)->post('/visits', ['starts_on' => $start, 'ends_on' => $end]);
    if ($valid) {
        $response->assertSessionHasNoErrors()->assertRedirect('/visits');
        $this->assertDatabaseHas('visits', ['starts_on' => $start, 'ends_on' => $end]);
    } else {
        $response->assertSessionHasErrors();
        $this->assertDatabaseEmpty('visits');
    }
})->with([
    ['2026-10-20', '2026-10-20', true],
    ['2026-10-20', '2026-11-18', true],
    ['2026-10-20', '2026-11-19', false],
    ['2026-10-23', '2026-10-22', false],
    ['2026-10-19', '2026-10-20', false],
    ['2027-10-20', '2027-10-20', true],
    ['2027-10-20', '2027-10-21', false],
    ['2027-02-29', '2027-03-01', false],
    ['2026-13-01', '2026-13-02', false],
    ['2026-10-24', '2026-10-26', true],
]);

test('ongoing stays retain their original start but cannot move it into the past', function () {
    $member = User::factory()->withProfile()->create();
    $visit = Visit::factory()->for($member)->create(['starts_on' => '2026-10-18', 'ends_on' => '2026-10-23']);
    $this->actingAs($member)->patch('/visits/'.$visit->id, ['starts_on' => '2026-10-18', 'ends_on' => '2026-10-24'])->assertSessionHasNoErrors();
    $this->patch('/visits/'.$visit->id, ['starts_on' => '2026-10-19', 'ends_on' => '2026-10-24'])->assertSessionHasErrors('starts_on');
});

test('visits require an eligible onboarded member', function () {
    $this->post('/visits', [])->assertRedirect('/login');
    $this->actingAs(User::factory()->withProfile(false)->create())->post('/visits', [])->assertRedirect(route('onboarding.show'));
});

test('leap days and the Paris midnight boundary remain date only', function () {
    $this->travelTo('2028-02-28 23:30:00 UTC');
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member)->post('/visits', ['starts_on' => '2028-02-28', 'ends_on' => '2028-02-29'])->assertSessionHasErrors('starts_on');
    $this->post('/visits', ['starts_on' => '2028-02-29', 'ends_on' => '2028-03-01'])->assertSessionHasNoErrors();
});

test('stay mutations return to the validated workspace context', function () {
    $member = User::factory()->withProfile()->create();
    $context = route('visits.index', ['month' => today('Europe/Paris')->format('Y-m'), 'view' => 'calendar', 'search' => 'Élodie']);
    $data = ['starts_on' => today('Europe/Paris')->toDateString(), 'ends_on' => today('Europe/Paris')->toDateString()];
    $this->actingAs($member)->from($context)->post(route('visits.store'), $data)->assertRedirect($context);
    $visit = Visit::query()->sole();
    $this->from($context)->patch(route('visits.update', $visit), $data)->assertRedirect($context);
    $this->from($context)->delete(route('visits.destroy', $visit))->assertRedirect($context);
});

test('visit date validation uses translated field names and messages', function ($locale, $orderMessage, $formatMessage) {
    $member = User::factory()->withProfile()->create(['locale' => $locale]);
    $this->actingAs($member)->post('/visits', [
        'starts_on' => '2026-10-23', 'ends_on' => '2026-10-21',
    ])->assertSessionHasErrors(['ends_on' => $orderMessage]);
    $this->post('/visits', [
        'starts_on' => 'invalid', 'ends_on' => '2026-10-23',
    ])->assertSessionHasErrors(['starts_on' => $formatMessage]);
})->with([
    ['fr', 'Le champ dernier jour doit être une date postérieure ou égale au champ premier jour.', 'Le champ premier jour doit respecter le format Y-m-d.'],
    ['en', 'The last day field must be a date after or equal to first day.', 'The first day field must match the format Y-m-d.'],
]);
