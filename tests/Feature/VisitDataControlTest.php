<?php

use App\Actions\BuildUserDataExport;
use App\Actions\DeleteMember;
use App\Actions\RequestAccountDeletion;
use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-20 12:00:00');
});

test('personal export includes every stored own visit and its independent preference', function () {
    $owner = User::factory()->withProfile()->create(['visits_visibility' => 'matches']);
    $own = Visit::factory()->for($owner)->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-02']);
    Visit::factory()->for(User::factory())->create(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-02']);
    $data = app(BuildUserDataExport::class)->handle($owner);
    expect($data['visits_visibility'])->toBe('matches')
        ->and($data['visits'])->toHaveCount(1)
        ->and($data['visits'][0])->toBe([
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-02',
            'created_at' => $own->created_at->toIso8601String(), 'updated_at' => $own->updated_at->toIso8601String(),
        ]);
});

test('expired visits purge at thirty calendar days and freeze for banned owners', function () {
    $owner = User::factory()->create();
    $expired = Visit::factory()->for($owner)->create(['starts_on' => '2026-10-20', 'ends_on' => '2026-10-22']);
    // Exactly thirty days in Paris, including the autumn clock change.
    $boundary = Visit::factory()->for($owner)->create(['starts_on' => '2026-10-20', 'ends_on' => '2026-10-21']);
    $banned = User::factory()->create(['status' => UserStatus::Banned]);
    $frozen = Visit::factory()->for($banned)->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-01']);
    $this->artisan('visits:purge-expired')->assertSuccessful();
    expect($boundary->fresh())->toBeNull()->and($expired->fresh())->not->toBeNull()->and($frozen->fresh())->not->toBeNull();
    $this->artisan('visits:purge-expired')->assertSuccessful();
    expect(Visit::query()->count())->toBe(2);
    $banned->forceFill(['status' => UserStatus::Active])->save();
    $this->artisan('visits:purge-expired')->assertSuccessful();
    expect($frozen->fresh())->toBeNull();
});

test('purge rechecks a visit extended after its batch was selected', function () {
    $owner = User::factory()->create();
    $visit = Visit::factory()->for($owner)->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-01']);
    $changed = false;
    DB::listen(function ($event) use ($visit, &$changed) {
        if (! $changed && str_starts_with($event->sql, 'select') && str_contains($event->sql, '`users`') && str_contains($event->sql, 'for update')) {
            $changed = true;
            Visit::query()->whereKey($visit->id)->update(['ends_on' => '2026-11-21']);
        }
    });
    $this->artisan('visits:purge-expired')->assertSuccessful();
    expect($changed)->toBeTrue()->and($visit->fresh()->ends_on->toDateString())->toBe('2026-11-21');
});

test('account deletion hides visits immediately and the existing purge cascades them', function () {
    Queue::fake();
    $owner = User::factory()->withProfile()->create(['visits_visibility' => 'members']);
    $viewer = User::factory()->withProfile()->create();
    Visit::factory()->for($owner)->create(['starts_on' => '2026-11-21', 'ends_on' => '2026-11-21']);
    app(RequestAccountDeletion::class)->handle($owner);
    $this->actingAs($viewer)->get('/visits')->assertInertia(fn (Assert $page) => $page->where('days', fn ($days) => collect($days)->sum('otherCount') === 0));
    $requested = $owner->fresh()->deletion_requested_at->toIso8601String();
    $this->travel(31)->days();
    (new PurgeDeletedUser($owner->id, $requested))->handle();
    expect($owner->fresh())->toBeNull()->and(Visit::query()->count())->toBe(0);
});

test('administrative deletion removes only that members visits', function () {
    Mail::fake();
    $owner = User::factory()->withProfile()->create();
    $own = Visit::factory()->for($owner)->create();
    $other = Visit::factory()->for(User::factory())->create();
    app(DeleteMember::class)->handle($owner);
    expect($own->fresh())->toBeNull()->and($other->fresh())->not->toBeNull();
});

test('visit retention is scheduled daily with shared scheduler locks', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'visits:purge-expired'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('45 3 * * *')
        ->and($event->timezone)->toBe('Europe/Paris')->and($event->withoutOverlapping)->toBeTrue()->and($event->onOneServer)->toBeTrue();
});
