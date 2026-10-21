<?php

use App\Actions\CancelTrip;
use App\Actions\DecideTripRegistration;
use App\Actions\RemoveTripParticipant;
use App\Actions\RequestTripParticipation;
use App\Actions\UpdateTrip;
use App\Actions\WithdrawFromTrip;
use App\Enums\TripNotificationType;
use App\Events\GroupMessageNotificationBroadcast;
use App\Models\GroupChat;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use App\Notifications\TripLifecycleNotification;
use App\Support\MemberNotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->organizer = User::factory()->withProfile()->create();
    $this->member = User::factory()->withProfile()->create();
    $this->trip = Trip::factory()->for($this->organizer, 'organizer')->create(['audience' => 'members']);
    GroupChat::query()->create(['trip_id' => $this->trip->id]);
    Notification::fake();
    Mail::fake();
});

test('requests and decisions notify the right person only once', function (bool $accept) {
    $registration = app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    Notification::assertSentToTimes($this->organizer, TripLifecycleNotification::class, 1);
    Notification::assertSentTo($this->organizer, TripLifecycleNotification::class, fn ($notification) => $notification->type === TripNotificationType::Requested);
    app(DecideTripRegistration::class)->handle($this->organizer, $registration, $accept, $accept ? null : 'Motif privé');
    app(DecideTripRegistration::class)->handle($this->organizer, $registration, $accept, $accept ? null : 'Reprise');
    Notification::assertSentToTimes($this->member, TripLifecycleNotification::class, 1);
    Notification::assertSentTo($this->member, TripLifecycleNotification::class, function ($notification) use ($accept) {
        expect($notification->type)->toBe($accept ? TripNotificationType::Accepted : TripNotificationType::Refused)
            ->and(json_encode($notification->toArray($this->member)))->not->toContain('Motif privé', $this->trip->meeting_point);

        return true;
    });
    Mail::assertNothingSent();
})->with([true, false]);

test('withdrawal exclusion rendezvous and cancellation notify only their audience', function (string $change) {
    $registration = TripRegistration::factory()->for($this->trip)->for($this->member, 'user')->create(['status' => 'accepted']);
    $pending = TripRegistration::factory()->for($this->trip)->create(['status' => 'pending']);
    if ($change === 'withdrawn') {
        app(WithdrawFromTrip::class)->handle($this->member, $this->trip, 'Motif privé');
        app(WithdrawFromTrip::class)->handle($this->member, $this->trip, 'Reprise');
        $recipient = $this->organizer;
    } elseif ($change === 'removed') {
        app(RemoveTripParticipant::class)->handle($this->organizer, $registration, 'Motif privé');
        app(RemoveTripParticipant::class)->handle($this->organizer, $registration, 'Reprise');
        $recipient = $this->member;
    } elseif ($change === 'changed') {
        $data = $this->trip->only(['transport', 'direction', 'general_departure', 'destination', 'meeting_point', 'audience', 'car_seats']);
        $data['transport'] = $this->trip->transport->value;
        $data['direction'] = $this->trip->direction->value;
        $data['audience'] = $this->trip->audience->value;
        $data['departs_at'] = $this->trip->departs_at->setTimezone('Europe/Paris')->format('Y-m-d\TH:i');
        $data['meeting_point'] = 'Rendez-vous privé modifié';
        app(UpdateTrip::class)->handle($this->organizer, $this->trip, $data);
        app(UpdateTrip::class)->handle($this->organizer, $this->trip, $data);
        $recipient = $this->member;
    } else {
        app(CancelTrip::class)->handle($this->organizer, $this->trip, 'Motif privé');
        app(CancelTrip::class)->handle($this->organizer, $this->trip, 'Reprise');
        $recipient = $this->member;
    }
    Notification::assertSentToTimes($recipient, TripLifecycleNotification::class, 1);
    Notification::assertSentTo($recipient, TripLifecycleNotification::class, fn ($notification) => $notification->type->value === $change);
    if ($change === 'cancelled') {
        Notification::assertSentTo($pending->user, TripLifecycleNotification::class);
    } else {
        Notification::assertNotSentTo($pending->user, TripLifecycleNotification::class);
    }
})->with(['withdrawn', 'removed', 'changed', 'cancelled']);

test('trip preferences and targets keep removed members in their minimal own history', function () {
    $registration = TripRegistration::factory()->for($this->trip)->for($this->member, 'user')->create(['status' => 'removed', 'reason' => 'Privé']);
    $notification = new TripLifecycleNotification($this->trip->id, TripNotificationType::Removed, $registration->id);
    expect($notification->shouldSend($this->member, 'database'))->toBeTrue()
        ->and(app(MemberNotificationPresenter::class)->targetUrl($notification->toArray($this->member), $this->member))->toBe('/trips/'.$this->trip->id);
    $this->actingAs($this->member)->get('/trips/'.$this->trip->id)->assertOk()->assertInertia(fn ($page) => $page->missing('trip.meeting_point'));
    $outsider = User::factory()->withProfile()->create();
    expect($notification->shouldSend($outsider, 'database'))->toBeFalse();
    $this->member->notificationPreferences()->create(['category' => 'trips', 'enabled' => false]);
    expect($notification->shouldSend($this->member, 'database'))->toBeFalse();
    $this->actingAs($this->member)->patch(route('notification-preferences.update'), ['trips' => true])->assertSessionHasNoErrors();
    expect($notification->shouldSend($this->member, 'database'))->toBeTrue();
});

test('queued accepted trip notifications do not survive a block or deleted trip', function (string $change) {
    $registration = TripRegistration::factory()->for($this->trip)->for($this->member, 'user')->create(['status' => 'accepted']);
    $notification = new TripLifecycleNotification($this->trip->id, TripNotificationType::Accepted, $registration->id);
    $notification->id = (string) Str::uuid();
    $broadcast = unserialize(serialize(new GroupMessageNotificationBroadcast($this->member->id, $notification)));
    expect($broadcast->broadcastOn())->not->toBeEmpty();
    if ($change === 'block') {
        $this->member->blocksCreated()->create(['blocked_user_id' => $this->organizer->id]);
    } else {
        $this->trip->delete();
    }
    expect($broadcast->broadcastOn())->toBe([])->and($broadcast->broadcastWith())->toBe([]);
})->with(['block', 'deleted']);
