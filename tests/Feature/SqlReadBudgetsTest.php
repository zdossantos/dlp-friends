<?php

use App\Contracts\DiscoveryTieBreaker;
use App\Data\EventWorkspaceData;
use App\Data\GroupChatData;
use App\Data\VisitWorkspaceData;
use App\Models\Event;
use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\Profile;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use App\Models\Visit;
use App\Services\DiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('event lists share the batched group unread counts', function () {
    $viewer = User::factory()->withProfile()->create();
    $counts = [];
    foreach ([1, 9] as $added) {
        foreach (range(1, $added) as $number) {
            $event = Event::factory()->for($viewer, 'organizer')->create();
            $chat = $event->chat()->create();
            GroupChatMessage::factory()->for($chat)->create();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $data = app(EventWorkspaceData::class)->mine($viewer);
        $counts[] = count(DB::getQueryLog());
        DB::disableQueryLog();
        expect(array_column($data['organized'], 'chatUnreadCount'))->each->toBe(1);
    }
    expect($counts[1] - $counts[0])->toBeLessThanOrEqual(2);
});

test('discovery hydrates only five candidates and ranks ties beyond its first SQL chunk', function () {
    $viewer = User::factory()->withProfile()->create();
    $candidates = User::factory()->withProfile()->count(205)->create();
    Profile::query()->update(['visit_frequency' => null]);
    $hydrated = 0;
    $measuring = true;
    Profile::retrieved(function () use (&$hydrated, &$measuring): void {
        if ($measuring) {
            $hydrated++;
        }
    });
    $service = new DiscoveryService(new class implements DiscoveryTieBreaker
    {
        public function rank(int $profileId): int
        {
            return -$profileId;
        }
    });

    $results = $service->for($viewer);
    $measuring = false;

    expect($results->pluck('userId')->all())->toBe($candidates->reverse()->take(5)->pluck('id')->all())
        ->and($hydrated)->toBeLessThanOrEqual(6);
});

test('the group index does not add authorization and unread queries per group', function () {
    $viewer = User::factory()->withProfile()->create();
    $this->actingAs($viewer);
    $counts = [];
    foreach ([1, 9] as $added) {
        foreach (range(1, $added) as $number) {
            $event = Event::factory()->for($viewer, 'organizer')->create();
            $chat = $event->chat()->create();
            GroupChatMessage::factory()->for($chat)->create();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('conversations.index'))->assertInertia(fn (Assert $page) => $page->has('groups', count($counts) === 0 ? 1 : 10));
        $counts[] = count(DB::getQueryLog());
        DB::disableQueryLog();
    }
    expect($counts[1] - $counts[0])->toBeLessThanOrEqual(4);
});

test('group catch up loads viewer reactions without a query for each message', function () {
    $viewer = User::factory()->withProfile()->create();
    $chat = GroupChat::factory()->create();
    $messages = GroupChatMessage::factory()->count(20)->for($chat)->for($viewer, 'author')->create();
    $messages[0]->reactions()->create(['user_id' => $viewer->id]);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $data = GroupChatData::updates($chat, $viewer, 0);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($data)->toHaveCount(20)
        ->and($data[0]['reaction_count'])->toBe(1)
        ->and($data[0]['reacted_by_current_user'])->toBeTrue()
        ->and($data[1]['reacted_by_current_user'])->toBeFalse()
        ->and($count)->toBeLessThanOrEqual(4);
});

test('calendar query budget stays bounded for a populated thirty one day month', function () {
    $this->travelTo('2026-10-20 12:00:00');
    $viewer = User::factory()->withProfile()->create();
    foreach (range(1, 5) as $number) {
        $visitor = User::factory()->withProfile()->create(['visits_visibility' => 'members']);
        $visitor->profile->update(['display_name' => "Member {$number}"]);
        Visit::factory()->count(2)->for($visitor)->create(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-31']);
    }
    $viewer->load('profile', 'roles');
    $hydrated = 0;
    $measuring = true;
    Profile::retrieved(function () use (&$hydrated, &$measuring): void {
        if ($measuring) {
            $hydrated++;
        }
    });
    DB::enableQueryLog();
    DB::flushQueryLog();

    $data = app(VisitWorkspaceData::class)->build($viewer, ['month' => '2026-12', 'day' => '2026-12-01', 'view' => 'calendar', 'audience' => 'members', 'search' => '', 'member' => null]);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();
    $measuring = false;

    expect($data['days'])->toHaveCount(31);
    foreach ($data['days'] as $day) {
        expect($day['otherCount'])->toBe(5)
            ->and(array_column($day['preview'], 'display_name'))->toBe(['Member 1', 'Member 2', 'Member 3']);
    }
    expect($count)->toBeLessThanOrEqual(10)
        ->and($hydrated)->toBe(3);
});

test('trip lists preload accepted places without a count query for each trip', function (string $path) {
    $viewer = User::factory()->withProfile()->create();
    $trips = Trip::factory()->count(3)->for($viewer, 'organizer')->create(['transport' => 'car', 'car_seats' => 3]);
    foreach ($trips as $trip) {
        TripRegistration::factory()->for($trip)->create(['status' => 'accepted']);
        TripRegistration::factory()->for($trip)->create(['status' => 'pending']);
    }
    $this->actingAs($viewer);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('trips.data', 3)
        ->where('trips.data.0.remaining_places', 2)
        ->where('trips.data.1.remaining_places', 2)
        ->where('trips.data.2.remaining_places', 2));
    $individualCounts = collect(DB::getQueryLog())->filter(fn (array $query) => preg_match('/^select count\(\*\) as [`"]?aggregate[`"]? from [`"]trip_registrations[`"]/', $query['query']) === 1)->count();
    DB::disableQueryLog();

    expect($individualCounts)->toBe(0);
})->with(['/trips', '/trips/mine']);

test('trip list SQL stays bounded as the page fills', function (string $path, string $membership) {
    $viewer = User::factory()->withProfile()->create();
    $this->actingAs($viewer);
    $counts = [];
    foreach ([1, 9] as $added) {
        foreach (range(1, $added) as $number) {
            $owner = $membership === 'organizer' ? $viewer : User::factory()->withProfile()->create();
            $trip = Trip::factory()->for($owner, 'organizer')->create(['audience' => 'members']);
            if ($membership !== 'organizer') {
                TripRegistration::factory()->for($trip)->for($viewer)->create(['status' => $membership]);
            }
            $trip->chat()->create();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $size = count($counts) === 0 ? 1 : 10;
        $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('trips.data', $size)->missing('trips.data.0.meeting_point')
            ->where('trips.data.0.can_manage', $membership === 'organizer'));
        $counts[] = count(DB::getQueryLog());
        DB::disableQueryLog();
    }
    expect($counts[1] - $counts[0])->toBeLessThanOrEqual(4);
})->with(['/trips', '/trips/mine'])->with(['organizer', 'pending', 'accepted']);

test('notification page SQL stays bounded across distinct event targets', function () {
    $viewer = User::factory()->withProfile()->create();
    $this->actingAs($viewer);
    $counts = [];
    foreach ([1, 19] as $added) {
        foreach (range(1, $added) as $number) {
            $event = Event::factory()->create();
            $viewer->notifications()->create([
                'id' => (string) Str::uuid(), 'type' => 'test',
                'data' => ['category' => 'events', 'translation_key' => 'notifications.items.event_accepted', 'target_type' => 'event', 'target_id' => $event->id],
            ]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $size = count($counts) === 0 ? 1 : 20;
        $this->get(route('notifications.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data', $size));
        $counts[] = count(DB::getQueryLog());
        DB::disableQueryLog();
    }
    expect($counts[1] - $counts[0])->toBeLessThanOrEqual(4);
});
