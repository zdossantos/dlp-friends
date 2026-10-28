<?php

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProfileVisibility;
use App\Models\Block;
use App\Models\ConversationReport;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\Interest;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\Swipe;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->startOfDay());
});

test('usage report compares equal periods and fills empty days without exposing private data', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->withProfile()->create(['created_at' => '2026-10-25 00:00:00']);
    $other = User::factory()->withProfile()->create(['created_at' => '2026-10-24 23:59:59']);
    Swipe::query()->forceCreate(['actor_user_id' => $member->id, 'target_user_id' => $other->id, 'decision' => 'like', 'created_at' => '2026-10-25 00:00:00']);
    Swipe::query()->forceCreate(['actor_user_id' => $other->id, 'target_user_id' => $member->id, 'decision' => 'pass', 'created_at' => '2026-10-24 23:59:59']);
    $match = MemberMatch::query()->forceCreate(['user_low_id' => $member->id, 'user_high_id' => $other->id, 'created_at' => '2026-10-27 23:59:59']);
    $conversation = $match->conversation()->create();
    Message::factory()->for($conversation)->create(['author_user_id' => $member->id, 'content' => 'private-content-never-in-report', 'created_at' => '2026-10-25 23:59:59']);
    Message::factory()->for($conversation)->create(['author_user_id' => $member->id, 'created_at' => '2026-10-26 12:00:00']);
    Message::factory()->for($conversation)->create(['author_user_id' => $member->id, 'created_at' => '2026-10-28 00:00:00']);

    $this->actingAs($admin)->get('/admin/statistics?from=2026-10-25&to=2026-10-27')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.period.previousFrom', '2026-10-22')
            ->where('usage.period.previousTo', '2026-10-24')
            ->has('usage.daily', 3)
            ->where('usage.daily.0.date', '2026-10-25')
            ->where('usage.daily.0.registrations', 1)
            ->where('usage.daily.1.registrations', 0)
            ->where('usage.metrics.registrations.current', 1)
            ->where('usage.metrics.registrations.previous', 1)
            ->where('usage.metrics.likes.current', 1)
            ->where('usage.metrics.passes.previous', 1)
            ->where('usage.metrics.matches.current', 1)
            ->where('usage.metrics.privateMessages.current', 2)
            ->where('usage.metrics.contributors.current', 1)
            ->where('usage.metrics.contributors.previous', 1)
            ->where('usage.daily.1.contributors', 1)
            ->where('usage.conversations.withMessages', 1)
            ->where('usage.conversations.total', 1)
            ->where('usage', function ($usage): bool {
                $json = json_encode($usage);

                return ! str_contains($json, 'private-content-never-in-report')
                    && ! str_contains($json, 'example.')
                    && ! str_contains($json, 'author_user_id');
            }));
});

test('usage report excludes admin support partner-only accounts and system messages', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->partnerOnly()->create();
    $member = User::factory()->withProfile()->create();
    Swipe::query()->forceCreate(['actor_user_id' => $admin->id, 'target_user_id' => $member->id, 'decision' => 'like']);
    Swipe::query()->forceCreate(['actor_user_id' => $member->id, 'target_user_id' => $admin->id, 'decision' => 'like']);
    $support = MemberMatch::query()->forceCreate(['user_low_id' => $admin->id, 'user_high_id' => $member->id]);
    Message::factory()->for($support->conversation()->create())->create(['author_user_id' => $member->id]);
    $event = Event::factory()->create(['organizer_user_id' => $member->id]);
    $group = GroupChat::factory()->create(['event_id' => $event->id]);
    $message = GroupChatMessage::factory()->create(['group_chat_id' => $group->id, 'author_user_id' => $member->id]);
    GroupChatMessage::factory()->create(['group_chat_id' => $message->group_chat_id, 'author_user_id' => $member->id, 'system_type' => 'joined']);

    $this->actingAs($admin)->get('/admin/statistics')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.metrics.likes.current', 0)
            ->where('usage.metrics.matches.current', 0)
            ->where('usage.metrics.privateMessages.current', 0)
            ->where('usage.metrics.groupMessages.current', 1)
            ->where('usage.metrics.contributors.current', 1));
});

test('activation describes the current state of the registration cohort', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->withProfile()->create(['created_at' => '2026-10-01']);
    User::factory()->unverified()->create();
    User::factory()->create();
    $incompleteTutorial = User::factory()->withProfile(false)->create();
    $complete = User::factory()->withProfile()->create();
    $complete->profile->update(['visibility' => ProfileVisibility::Hidden]);

    $this->actingAs($admin)->get('/admin/statistics?from=2026-10-27&to=2026-10-27')
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.activation.registered', 4)
            ->where('usage.activation.verified', 3)
            ->where('usage.activation.profileCompleted', 2)
            ->where('usage.activation.tutorialCompleted', 1)
            ->where('usage.community.hiddenProfiles', 1)
            ->where('usage.community.members', 5));

    $incompleteTutorial->productOnboarding()->create(['status' => ProductOnboardingStatus::Completed]);
    $this->get('/admin/statistics?from=2026-10-27&to=2026-10-27')
        ->assertInertia(fn (Assert $page) => $page->where('usage.activation.tutorialCompleted', 2));
});

test('usage filters reject invalid unsafe or unbounded date ranges', function (array $query) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson(route('admin.statistics.index', $query))->assertUnprocessable();
})->with([
    [['from' => 'not-a-date', 'to' => '2026-10-27']],
    [['from' => ['2026-10-20'], 'to' => '2026-10-27']],
    [['from' => '2026-10-20', 'to' => ['2026-10-27']]],
    [['from' => '2026-10-27', 'to' => '2026-10-26']],
    [['from' => '2026-10-28', 'to' => '2026-10-28']],
    [['from' => '2025-10-25', 'to' => '2026-10-27']],
    [['from' => '2026-10-20']],
    [['to' => '2026-10-27']],
]);

test('empty member activity is represented by zeros and admin access remains enforced', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/statistics')
        ->assertInertia(fn (Assert $page) => $page
            ->has('usage.daily', 30)
            ->where('usage.metrics.contributors.current', 0)
            ->where('usage.metrics.registrations.previous', 0)
            ->where('usage.activation.registered', 0)
            ->where('usage.interests', []));

    $this->actingAs(User::factory()->create())->get('/admin/statistics')->assertForbidden();
    $this->actingAs(User::factory()->partnerOnly()->create())->get('/admin/statistics')->assertForbidden();
});

test('activities and safety totals count member records and deduplicate the same contributor across features', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create();
    $event = Event::factory()->create(['organizer_user_id' => $member->id]);
    $trip = Trip::factory()->create(['organizer_user_id' => $member->id]);
    Visit::factory()->create(['user_id' => $member->id]);
    EventRegistration::factory()->create(['event_id' => $event->id, 'user_id' => $other->id]);
    TripRegistration::factory()->create(['trip_id' => $trip->id, 'user_id' => $other->id]);
    Block::factory()->create(['blocker_user_id' => $member->id, 'blocked_user_id' => $other->id]);
    $match = MemberMatch::query()->create(['user_low_id' => $member->id, 'user_high_id' => $other->id]);
    $conversation = $match->conversation()->create();
    ConversationReport::query()->create(['conversation_id' => $conversation->id, 'reporter_user_id' => $member->id, 'target_user_id' => $other->id, 'reason' => 'spam', 'details' => 'never-expose-report-details']);
    Event::factory()->create(['organizer_user_id' => $admin->id]);
    Visit::factory()->create(['user_id' => $admin->id]);
    Trip::factory()->create(['organizer_user_id' => $admin->id]);
    EventRegistration::factory()->create(['event_id' => $event->id, 'user_id' => $admin->id]);
    TripRegistration::factory()->create(['trip_id' => $trip->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)->get('/admin/statistics?from=2026-10-27&to=2026-10-27')
        ->assertInertia(function (Assert $page) {
            foreach (['events', 'visits', 'trips', 'eventRegistrations', 'tripRegistrations', 'blocks', 'reports', 'contributors'] as $metric) {
                $page->where("usage.metrics.{$metric}.current", 1)
                    ->where("usage.daily.0.{$metric}", 1);
            }
            $page->where('usage', fn ($usage) => ! str_contains(json_encode($usage), 'never-expose-report-details'));
        });
});

test('interest snapshot uses selected active interests and current member roles with localized names', function () {
    $admin = User::factory()->admin()->withProfile()->create(['locale' => 'en']);
    $member = User::factory()->withProfile()->create(['created_at' => '2026-09-01']);
    $selected = Interest::factory()->create(['name' => 'Spectacles', 'name_en' => 'Shows']);
    $unselected = Interest::factory()->create();
    $archived = Interest::factory()->create(['is_active' => false]);
    $adminOnly = Interest::factory()->create();
    $member->profile->interestHistory()->attach($selected, ['is_selected' => true]);
    $member->profile->interestHistory()->attach($unselected, ['is_selected' => false]);
    $member->profile->interestHistory()->attach($archived, ['is_selected' => true]);
    $admin->profile->interestHistory()->attach($selected, ['is_selected' => true]);
    $admin->profile->interestHistory()->attach($adminOnly, ['is_selected' => true]);

    $this->actingAs($admin)->get('/admin/statistics?from=2026-10-27&to=2026-10-27')
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.metrics.registrations.current', 0)
            ->where('usage.interests', [['name' => 'Shows', 'count' => 1]]));
});

test('the inclusive maximum period of 366 days is accepted', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/statistics?from=2025-10-27&to=2026-10-27')
        ->assertInertia(fn (Assert $page) => $page->has('usage.daily', 366)->where('usage.period.days', 366));
});

test('the clean dashboard stays separate from detailed admin statistics', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard')
            ->has('stats.totalAccounts')->has('recentRegistrations')->missing('usage'));

    $this->get('/admin/statistics')
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Statistics')
            ->has('usage')->missing('stats')->missing('recentRegistrations'));
});
