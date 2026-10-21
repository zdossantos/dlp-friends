<?php

use App\Actions\BlockUser;
use App\Actions\DecideTripRegistration;
use App\Actions\DeleteMember;
use App\Actions\RequestAccountDeletion;
use App\Actions\SetMemberBan;
use App\Enums\TripRegistrationStatus;
use App\Events\GroupChatAccessChanged;
use App\Models\Block;
use App\Models\ConversationReport;
use App\Models\GroupChat;
use App\Models\MemberMatch;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([GroupChatAccessChanged::class]);
    $this->organizer = User::factory()->withProfile()->create();
    $this->first = User::factory()->withProfile()->create();
    $this->second = User::factory()->withProfile()->create();
    $this->trip = Trip::factory()->for($this->organizer, 'organizer')->create(['audience' => 'members']);
    $this->chat = GroupChat::query()->create(['trip_id' => $this->trip->id]);
    $this->firstRegistration = TripRegistration::factory()->for($this->trip)->for($this->first, 'user')->create(['status' => 'accepted']);
    $this->secondRegistration = TripRegistration::factory()->for($this->trip)->for($this->second, 'user')->create(['status' => 'accepted']);
});

test('organizer member blocks revoke the participant in both directions without voluntary proof', function (bool $organizerBlocks) {
    [$blocker, $blocked] = $organizerBlocks ? [$this->organizer, $this->first] : [$this->first, $this->organizer];
    app(BlockUser::class)->handle($blocker, $blocked);
    app(BlockUser::class)->handle($blocker, $blocked);
    expect($this->firstRegistration->fresh()->status)->toBe(TripRegistrationStatus::Blocked)
        ->and($this->secondRegistration->fresh()->status)->toBe(TripRegistrationStatus::Accepted)
        ->and(TripDeparture::query()->count())->toBe(0)
        ->and(Gate::forUser($this->first)->allows('view', $this->chat))->toBeFalse();
    Event::assertDispatchedTimes(GroupChatAccessChanged::class, 1);
    Block::query()->delete();
    expect($this->firstRegistration->fresh()->status)->toBe(TripRegistrationStatus::Blocked);
})->with([true, false]);

test('a participant block needs an additional explicit confirmation before any side effect', function () {
    try {
        app(BlockUser::class)->handle($this->first, $this->second);
        $this->fail('Missing group departure confirmation');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('confirm_trip_departures');
    }
    expect(Block::query()->count())->toBe(0)
        ->and($this->firstRegistration->fresh()->status)->toBe(TripRegistrationStatus::Accepted)
        ->and($this->secondRegistration->fresh()->status)->toBe(TripRegistrationStatus::Accepted);
    Event::assertNothingDispatched();
});

test('confirmed participant blocking leaves every common group only for the blocker', function () {
    $otherTrip = Trip::factory()->for($this->organizer, 'organizer')->create();
    GroupChat::query()->create(['trip_id' => $otherTrip->id]);
    $otherFirst = TripRegistration::factory()->for($otherTrip)->for($this->first, 'user')->create(['status' => 'accepted']);
    $otherSecond = TripRegistration::factory()->for($otherTrip)->for($this->second, 'user')->create(['status' => 'accepted']);
    app(BlockUser::class)->handle($this->first, $this->second, true);
    app(BlockUser::class)->handle($this->first, $this->second, true);
    expect($this->firstRegistration->fresh()->status)->toBe(TripRegistrationStatus::Blocked)
        ->and($otherFirst->fresh()->status)->toBe(TripRegistrationStatus::Blocked)
        ->and($this->secondRegistration->fresh()->status)->toBe(TripRegistrationStatus::Accepted)
        ->and($otherSecond->fresh()->status)->toBe(TripRegistrationStatus::Accepted)
        ->and(TripDeparture::query()->count())->toBe(0);
    Event::assertDispatchedTimes(GroupChatAccessChanged::class, 2);
});

test('the block endpoint validates confirmation and never treats a false value as consent', function () {
    $this->actingAs($this->first)->post(route('members.block', $this->second), ['confirm_trip_departures' => false])->assertSessionHasErrors('confirm_trip_departures');
    expect(Block::query()->count())->toBe(0);
    $this->actingAs($this->first)->post(route('members.block', $this->second), ['confirm_trip_departures' => true])->assertRedirect();
    expect($this->firstRegistration->fresh()->status)->toBe(TripRegistrationStatus::Blocked);
});

test('a committed block prevents a stale pending acceptance', function () {
    $this->firstRegistration->update(['status' => 'pending']);
    app(BlockUser::class)->handle($this->first, $this->second);
    expect(fn () => app(DecideTripRegistration::class)->handle($this->organizer, $this->firstRegistration, true, null))->toThrow(AuthorizationException::class);
    expect($this->firstRegistration->fresh()->status)->toBe(TripRegistrationStatus::Pending);
});

test('inactivation cancels owned future trips and revokes joined trips without departure proof', function (string $operation) {
    Queue::fake();
    Mail::fake();
    $owned = Trip::factory()->for($this->first, 'organizer')->create();
    GroupChat::query()->create(['trip_id' => $owned->id]);
    match ($operation) {
        'ban' => app(SetMemberBan::class)->handle(User::factory()->admin()->create(), $this->first, true, 'Motif administratif'),
        'deletion' => app(RequestAccountDeletion::class)->handle($this->first),
        'admin_deletion' => app(DeleteMember::class)->handle($this->first),
    };
    expect($owned->fresh()?->cancelled_at)->not->toBeNull()
        ->and($this->firstRegistration->fresh()?->status)->toBe(TripRegistrationStatus::Blocked)
        ->and(TripDeparture::query()->count())->toBe(0)
        ->and($owned->fresh()?->cancellation_reason)->toBeNull();
    if ($operation !== 'admin_deletion') {
        expect(Gate::forUser($this->first->fresh())->allows('view', $this->chat))->toBeFalse();
    }
})->with(['ban', 'deletion', 'admin_deletion']);

test('hiding a profile only affects discovery and retains acquired group membership', function () {
    $this->first->profile->update(['visibility' => 'hidden']);
    expect(Gate::forUser($this->first)->allows('view', $this->chat))->toBeTrue()
        ->and($this->firstRegistration->fresh()->status)->toBe(TripRegistrationStatus::Accepted);
});

test('reporting a conversation with a block also confirms common trip departures atomically', function () {
    $match = MemberMatch::query()->create(['user_low_id' => $this->first->id, 'user_high_id' => $this->second->id]);
    $conversation = $match->conversation()->create();
    $payload = ['reason' => 'other', 'details' => 'Contact répété', 'block' => true, 'confirmed' => true];
    $url = route('conversations.reports.store', $conversation);
    $this->actingAs($this->first)->post($url, $payload)->assertSessionHasErrors('confirm_trip_departures');
    expect(Block::query()->count())->toBe(0)->and(ConversationReport::query()->count())->toBe(0);
    $this->post($url, [...$payload, 'confirm_trip_departures' => true])->assertSessionHasNoErrors()->assertRedirect();
    expect(Block::query()->count())->toBe(1)->and(ConversationReport::query()->count())->toBe(1)
        ->and($this->firstRegistration->fresh()->status)->toBe(TripRegistrationStatus::Blocked);
});
