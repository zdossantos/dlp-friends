<?php

use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->travelTo('2026-10-20 12:00:00');
});

function publicVisitor(string $name = 'Camille'): User
{
    $user = User::factory()->withProfile()->create(['visits_visibility' => 'members']);
    $user->profile->update(['display_name' => $name]);
    Visit::factory()->for($user)->create(['starts_on' => '2026-10-23', 'ends_on' => '2026-10-25']);

    return $user;
}
function visitDay(Assert $page, string $date, int $count): Assert
{
    return $page->where('days', fn ($days) => collect($days)->firstWhere('date', $date)['otherCount'] === $count);
}

test('calendar counts distinct authorized others including both boundaries and keeps own stays separate', function () {
    $viewer = User::factory()->withProfile()->create();
    Visit::factory()->count(2)->for($viewer)->create(['starts_on' => '2026-10-23', 'ends_on' => '2026-10-25']);
    for ($i = 0; $i < 24; $i++) {
        $other = publicVisitor();
        Visit::factory()->for($other)->create(['starts_on' => '2026-10-24', 'ends_on' => '2026-10-24']);
        if ($i < 11) {
            MemberMatch::query()->create(['user_low_id' => $viewer->id, 'user_high_id' => $other->id]);
        }
    }
    $hidden = publicVisitor();
    $hidden->forceFill(['visits_visibility' => 'hidden'])->save();
    $blocked = publicVisitor();
    Block::query()->create(['blocker_user_id' => $blocked->id, 'blocked_user_id' => $viewer->id]);
    $this->actingAs($viewer)->get('/visits?audience=members&month=2026-10&day=2026-10-24')->assertInertia(function (Assert $page) {
        $page->component('Visits/Index', false)->has('ownVisits', 2);
        visitDay($page, '2026-10-23', 24);
        visitDay($page, '2026-10-24', 24);
        visitDay($page, '2026-10-25', 24);
        $page->where('days', fn ($days) => count(collect($days)->firstWhere('date', '2026-10-24')['preview']) === 3);
    });
    $this->get('/visits?month=2026-10&audience=matches')->assertInertia(fn (Assert $page) => visitDay($page, '2026-10-24', 11)->has('ownVisits', 2));
});

test('the full list paginates distinct people and rechecks privacy on every page', function () {
    $viewer = User::factory()->withProfile()->create();
    $first = null;
    for ($i = 0; $i < 105; $i++) {
        $user = publicVisitor(sprintf('Member %03d', $i));
        $first ??= $user;
    }
    $url = '/visits/attendees?audience=members&month=2026-10&day=2026-10-24';
    $ids = [];
    for ($page = 1; $page <= 4; $page++) {
        $this->actingAs($viewer)->get($url.'&page='.$page)->assertInertia(function (Assert $response) use (&$ids, $page) {
            $response->where('panel.members.meta.total', 105)->has('panel.members.data', $page === 4 ? 15 : 30)
                ->where('panel.members.data', function ($members) use (&$ids) {
                    foreach ($members as $member) {
                        $ids[] = $member['id'];
                    }

                    return true;
                });
        });
    }
    expect(array_unique($ids))->toHaveCount(105);
    Block::query()->create(['blocker_user_id' => $viewer->id, 'blocked_user_id' => $first->id]);
    $this->get($url)->assertInertia(fn (Assert $p) => $p->where('panel.members.meta.total', 104));
});

test('name search is accent insensitive literal and never filters the owners stays', function () {
    $viewer = User::factory()->withProfile()->create();
    Visit::factory()->for($viewer)->create(['starts_on' => '2026-10-24', 'ends_on' => '2026-10-24']);
    $elodie = publicVisitor('Élodie');
    publicVisitor('Élodie');
    publicVisitor("O'Neil_%");
    $private = publicVisitor('Élodie');
    $private->forceFill(['visits_visibility' => 'hidden'])->save();
    foreach (['elodie' => 2, 'ELODIE' => 2, '%' => 1, '_' => 1, "O'Neil" => 1, 'inconnu' => 0] as $search => $count) {
        $this->actingAs($viewer)->get('/visits?audience=members&month=2026-10&search='.urlencode($search))->assertInertia(fn (Assert $p) => visitDay($p, '2026-10-24', $count)->has('ownVisits', 1));
    }
    $this->get('/visits?audience=members&month=2026-10&member='.$elodie->id)->assertInertia(fn (Assert $p) => visitDay($p, '2026-10-24', 1));
    $this->get('/visits?audience=members&month=2026-10&member='.$private->id)->assertInertia(fn (Assert $p) => visitDay($p, '2026-10-24', 0));
});

test('invalid calendar parameters cannot widen queries', function ($query, $field) {
    $this->actingAs(User::factory()->withProfile()->create())->get('/visits?'.$query)->assertSessionHasErrors($field);
})->with([['month=2026-13', 'month'], ['day=2026-02-30', 'day'], ['view=unknown', 'view'], ['audience=hidden', 'audience'], ['member=abc', 'member'], ['page=-1', 'page'], ['search='.str_repeat('a', 101), 'search'], ['month=2026-10&day=2026-11-01', 'day']]);

test('direct attendee links never reveal past days of an ongoing stay', function () {
    $viewer = User::factory()->withProfile()->create();
    $other = publicVisitor();
    $other->visits()->update(['starts_on' => '2026-10-18', 'ends_on' => '2026-10-23']);
    $this->actingAs($viewer)->get('/visits/attendees?audience=members&month=2026-10&day=2026-10-18')
        ->assertInertia(fn (Assert $page) => $page->where('panel.members.meta.total', 0)->has('panel.members.data', 0));
    $this->get('/visits/attendees?audience=members&month=2026-10&day=2026-10-20')
        ->assertInertia(fn (Assert $page) => $page->where('panel.members.meta.total', 1));
});

test('visits default to the calendar and matches while explicit filters remain available', function () {
    $viewer = User::factory()->withProfile()->create();
    $this->actingAs($viewer)->get('/visits')->assertInertia(fn (Assert $page) => $page
        ->where('filters.view', 'calendar')->where('filters.audience', 'matches')
        ->where('filters.day', '2026-10-20'));
    $this->get('/visits?view=agenda&audience=members')->assertInertia(fn (Assert $page) => $page
        ->where('filters.view', 'agenda')->where('filters.audience', 'members'));
});
