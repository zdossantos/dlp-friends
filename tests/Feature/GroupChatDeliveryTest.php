<?php

use App\Actions\DecideTripRegistration;
use App\Actions\RemoveTripParticipant;
use App\Actions\WithdrawFromTrip;
use App\Events\GroupChatAccessChanged;
use App\Events\GroupChatMessageReactionUpdated;
use App\Events\GroupChatMessageSent;
use App\Models\GroupChat;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->organizer = User::factory()->withProfile()->create();
    $this->author = User::factory()->withProfile()->create();
    $this->member = User::factory()->withProfile()->create();
    $this->trip = Trip::factory()->for($this->organizer, 'organizer')->create();
    $this->chat = GroupChat::query()->create(['trip_id' => $this->trip->id]);
    foreach ([$this->author, $this->member] as $user) {
        TripRegistration::factory()->for($this->trip)->for($user)->create(['status' => 'accepted']);
    }
    $this->message = $this->chat->messages()->create(['author_user_id' => $this->author->id, 'content' => 'Privé']);
});

test('queued delivery resolves fresh personal recipients and never uses a shared channel', function (bool $reaction) {
    $event = $reaction
        ? new GroupChatMessageReactionUpdated($this->chat->id, $this->message->id, $this->author->id, 1, true)
        : new GroupChatMessageSent($this->message);
    $jobEvent = unserialize(serialize($event));
    $this->trip->registrations()->where('user_id', $this->member->id)->update(['status' => 'removed']);
    $channels = collect($jobEvent->broadcastOn())->pluck('name')->all();
    expect($channels)->toContain('private-App.Models.User.'.$this->organizer->id)
        ->not->toContain('private-App.Models.User.'.$this->member->id)
        ->not->toContain('private-group-chat.'.$this->chat->id)
        ->not->toContain('private-event-chat.'.$this->chat->id);
    expect($jobEvent->broadcastWith()['group_chat_id'])->toBe($this->chat->id);
})->with([false, true]);

test('queued message and reaction stop after author withdrawal ban organizer loss or deletion', function (string $change, bool $reaction) {
    $event = $reaction
        ? new GroupChatMessageReactionUpdated($this->chat->id, $this->message->id, $this->author->id, 1, true)
        : new GroupChatMessageSent($this->message);
    $serialized = serialize($event);
    match ($change) {
        'withdrawal' => $this->trip->registrations()->where('user_id', $this->author->id)->update(['status' => 'withdrawn']),
        'ban' => $this->author->forceFill(['status' => 'banned'])->save(),
        'organizer' => $this->organizer->forceFill(['status' => 'banned'])->save(),
        'deleted' => $this->message->delete(),
    };
    $event = unserialize($serialized);
    expect($event->broadcastWhen())->toBeFalse();
    expect($event->broadcastOn())->toBe([]);
    expect($event->broadcastWith())->toBe([]);
})->with(['withdrawal', 'ban', 'organizer', 'deleted'])->with([false, true]);

test('a delayed access grant reflects current exclusion without private group details', function () {
    $event = unserialize(serialize(new GroupChatAccessChanged($this->chat->id, $this->member->id, 'granted')));
    $this->trip->registrations()->where('user_id', $this->member->id)->update(['status' => 'removed']);
    expect($event->broadcastWith())->toBe([
        'group_chat_id' => $this->chat->id,
        'context' => 'trip',
        'context_id' => $this->trip->id,
        'access' => 'revoked',
    ]);
    expect(collect($event->broadcastOn())->pluck('name')->all())->toBe(['private-App.Models.User.'.$this->member->id]);
});

test('trip membership transitions notify personal access once', function (string $transition) {
    Event::fake([GroupChatAccessChanged::class]);
    $registration = $this->trip->registrations()->where('user_id', $this->member->id)->firstOrFail();
    if ($transition === 'accept') {
        $registration->update(['status' => 'pending']);
        $this->trip->update(['audience' => 'members']);
    }
    $run = fn () => match ($transition) {
        'accept' => app(DecideTripRegistration::class)->handle($this->organizer, $registration, true, null),
        'leave' => app(WithdrawFromTrip::class)->handle($this->member, $this->trip, 'Indisponible'),
        'remove' => app(RemoveTripParticipant::class)->handle($this->organizer, $registration, 'Indisponible'),
    };
    $run();
    $run();
    Event::assertDispatchedTimes(GroupChatAccessChanged::class, 1);
    Event::assertDispatched(GroupChatAccessChanged::class, fn ($event) => $event->userId === $this->member->id && $event->groupChatId === $this->chat->id);
})->with(['accept', 'leave', 'remove']);
