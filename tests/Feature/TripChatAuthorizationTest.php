<?php

use App\Actions\MarkGroupChatRead;
use App\Actions\SendGroupChatMessage;
use App\Actions\SetGroupChatMessageReaction;
use App\Enums\TripRegistrationStatus;
use App\Models\GroupChat;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use App\Notifications\NewGroupMessageNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-10-21 10:00:00 UTC');
    $this->organizer = User::factory()->withProfile()->create();
    $this->member = User::factory()->withProfile()->create();
    $this->trip = Trip::factory()->for($this->organizer, 'organizer')->create();
    $this->chat = GroupChat::query()->create(['trip_id' => $this->trip->id]);
});

test('only accepted trip participants and the organizer can read send mark or react', function (?string $status, bool $allowed) {
    if ($status !== null) {
        TripRegistration::factory()->for($this->trip)->for($this->member)->create(['status' => $status]);
    }
    expect(Gate::forUser($this->member)->allows('view', $this->chat))->toBe($allowed);
    expect(Gate::forUser($this->organizer)->allows('send', $this->chat))->toBeTrue();
    $message = $this->chat->messages()->create(['author_user_id' => $this->organizer->id, 'content' => 'Bonjour']);
    if (! $allowed) {
        expect(fn () => app(SendGroupChatMessage::class)->handle($this->member, $this->chat, 'Secret'))->toThrow(AuthorizationException::class);
        expect(fn () => app(MarkGroupChatRead::class)->handle($this->member, $this->chat, $message->id))->toThrow(AuthorizationException::class);
        expect(fn () => app(SetGroupChatMessageReaction::class)->handle($this->member, $this->chat, $message, true))->toThrow(AuthorizationException::class);
    }
})->with([[null, false], ['pending', false], ['accepted', true], ['refused', false], ['withdrawn', false], ['removed', false], ['blocked', false]]);

test('trip chat is writable until exactly twenty four hours after departure and cancellation closes it immediately', function () {
    $this->trip->update(['departs_at' => now()->subHours(24)->addSecond()]);
    expect(Gate::forUser($this->organizer)->allows('send', $this->chat->fresh()))->toBeTrue();
    $this->travel(1)->second();
    expect($this->chat->fresh()->isReadOnly())->toBeTrue();
    expect(Gate::forUser($this->organizer)->allows('view', $this->chat->fresh()))->toBeTrue();
    $this->trip->update(['departs_at' => now()->addDay(), 'cancelled_at' => now()]);
    expect($this->chat->fresh()->readOnlyReason())->toBe('cancelled');
});

test('a message or read cursor cannot cross group contexts even for an authorized organizer', function () {
    $other = GroupChat::factory()->create();
    $message = $other->messages()->create(['author_user_id' => $this->member->id, 'content' => 'Autre groupe']);
    expect(fn () => app(MarkGroupChatRead::class)->handle($this->organizer, $this->chat, $message->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(SetGroupChatMessageReaction::class)->handle($this->organizer, $this->chat, $message, true))->toThrow(ModelNotFoundException::class);
    $this->assertDatabaseEmpty('group_chat_reads');
});

test('stale models do not retain access after exclusion', function () {
    $registration = TripRegistration::factory()->for($this->trip)->for($this->member)->create(['status' => TripRegistrationStatus::Accepted]);
    expect(Gate::forUser($this->member)->allows('view', $this->chat))->toBeTrue();
    $registration->update(['status' => TripRegistrationStatus::Removed]);
    expect(Gate::forUser($this->member)->allows('view', $this->chat))->toBeFalse();
});

test('accepted trip members can exchange messages and group notifications use the trip context', function () {
    Notification::fake();
    TripRegistration::factory()->for($this->trip)->for($this->member)->create(['status' => TripRegistrationStatus::Accepted]);
    $message = app(SendGroupChatMessage::class)->handle($this->member, $this->chat, 'À demain');
    expect($message->group_chat_id)->toBe($this->chat->id);
    app(MarkGroupChatRead::class)->handle($this->organizer, $this->chat, $message->id);
    expect($this->chat->reads()->sole()->last_read_message_id)->toBe($message->id);
    $notification = new NewGroupMessageNotification($message);
    expect($notification->toArray($this->organizer)['target_type'])->toBe('trip_chat');
    expect($notification->toArray($this->organizer)['target_id'])->toBe($this->trip->id);
    expect($notification->webPushTarget($this->organizer)->url)->toBe('/trips/'.$this->trip->id.'/chat');
});
