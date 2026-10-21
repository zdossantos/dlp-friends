<?php

use App\Actions\BuildUserDataExport;
use App\Actions\DecideEventRegistration;
use App\Actions\RegisterForEvent;
use App\Actions\RemoveEventParticipant;
use App\Actions\WithdrawFromEvent;
use App\Data\GroupChatData;
use App\Enums\EventRegistrationMode;
use App\Events\EventChatMessageSent;
use App\Models\Event;
use App\Models\GroupChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('accepted registrations appear in history before the member sends a message', function () {
    $event = Event::factory()->create();
    $event->chat()->create();
    $member = User::factory()->withProfile()->create();
    app(RegisterForEvent::class)->handle($member, $event);

    $this->actingAs($event->organizer)->get(route('events.chat.show', $event))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('messages.data', 1)
        ->where('messages.data.0.system_type', 'joined')
        ->where('messages.data.0.author_user_id', $member->id)
        ->where('messages.data.0.author.display_name', $member->profile->display_name));
});

test('pending refused and withdrawn requests never announce membership', function () {
    $event = Event::factory()->create(['registration_mode' => EventRegistrationMode::Manual]);
    $event->chat()->create();
    $refused = app(RegisterForEvent::class)->handle(User::factory()->withProfile()->create(), $event);
    $withdrawn = app(RegisterForEvent::class)->handle(User::factory()->withProfile()->create(), $event);
    app(DecideEventRegistration::class)->handle($event->organizer, $refused, false);
    app(WithdrawFromEvent::class)->handle($withdrawn->user, $event);
    expect(GroupChatMessage::query()->count())->toBe(0);
});

test('manual acceptance announces membership exactly once', function () {
    $event = Event::factory()->create(['registration_mode' => EventRegistrationMode::Manual]);
    $event->chat()->create();
    $registration = app(RegisterForEvent::class)->handle(User::factory()->withProfile()->create(), $event);
    app(DecideEventRegistration::class)->handle($event->organizer, $registration, true);
    try {
        app(DecideEventRegistration::class)->handle($event->organizer, $registration, true);
        $this->fail('Duplicate acceptance should fail.');
    } catch (ValidationException) {
        expect(GroupChatMessage::query()->count())->toBe(1);
        expect(GroupChatMessage::query()->firstOrFail()->system_type?->value)->toBe('joined');
    }
});

test('leaving rejoining and organizer removal have distinct ordered announcements', function () {
    $event = Event::factory()->create();
    $event->chat()->create();
    $member = User::factory()->withProfile()->create();
    app(RegisterForEvent::class)->handle($member, $event);
    app(WithdrawFromEvent::class)->handle($member, $event);
    $registration = app(RegisterForEvent::class)->handle($member, $event);
    app(RemoveEventParticipant::class)->handle($event->organizer, $registration);

    expect(GroupChatMessage::query()->orderBy('id')->get()->map(fn ($message) => $message->system_type?->value)->all())
        ->toBe(['joined', 'left', 'joined', 'removed']);
    expect(GroupChatMessage::query()->where('author_user_id', $member->id)->count())->toBe(4);
    $this->actingAs($member)->get(route('events.chat.show', $event))->assertForbidden();
});

test('membership announcements use the realtime contract and unread cursor', function () {
    EventFacade::fake([EventChatMessageSent::class]);
    $event = Event::factory()->create();
    $event->chat()->create();
    $member = User::factory()->withProfile()->create();
    app(RegisterForEvent::class)->handle($member, $event);
    $message = GroupChatMessage::query()->firstOrFail();

    EventFacade::assertDispatched(EventChatMessageSent::class, fn ($broadcast) => $broadcast->broadcastWith()['system_type'] === 'joined'
        && $broadcast->broadcastWith()['author_user_id'] === $member->id
        && $broadcast->broadcastOn()[0]->name === "private-event-chat.{$message->group_chat_id}");
    expect(GroupChatData::unreadCount($message->groupChat, $event->organizer))->toBe(1);
    $this->actingAs($event->organizer)->postJson(route('events.chat.read.store', $event), [
        'last_read_message_id' => $message->id,
    ])->assertSuccessful();
    expect(GroupChatData::unreadCount($message->groupChat, $event->organizer))->toBe(0);
});

test('membership announcements cannot be liked or forged by a chat sender', function () {
    $event = Event::factory()->create();
    $event->chat()->create();
    $member = User::factory()->withProfile()->create();
    app(RegisterForEvent::class)->handle($member, $event);
    $message = GroupChatMessage::query()->firstOrFail();
    $this->actingAs($event->organizer)->postJson(route('events.chat.messages.like', [$event, $message]))->assertForbidden();
    $this->postJson(route('events.chat.messages.store', $event), [
        'content' => 'Bonjour', 'system_type' => 'joined',
    ])->assertCreated()->assertJsonPath('data.system_type', null);
    expect($message->reactions()->count())->toBe(0);
});

test('rolling back a registration also removes its announcement', function () {
    $event = Event::factory()->create();
    $event->chat()->create();
    DB::beginTransaction();
    app(RegisterForEvent::class)->handle(User::factory()->withProfile()->create(), $event);
    expect(GroupChatMessage::query()->count())->toBe(1);
    DB::rollBack();
    expect(GroupChatMessage::query()->count())->toBe(0);
    expect($event->registrations()->count())->toBe(0);
});

test('pending removals and failed registrations do not leave announcements', function () {
    $event = Event::factory()->create(['registration_mode' => EventRegistrationMode::Manual]);
    $registration = app(RegisterForEvent::class)->handle(User::factory()->withProfile()->create(), $event);
    app(RemoveEventParticipant::class)->handle($event->organizer, $registration);
    expect(GroupChatMessage::query()->count())->toBe(0);

    $full = Event::factory()->create(['capacity' => 2]);
    app(RegisterForEvent::class)->handle($registration->user, $full);
    foreach ([$registration->user, User::factory()->withProfile()->create()] as $member) {
        try {
            app(RegisterForEvent::class)->handle($member, $full);
            $this->fail('Duplicate or full registration should fail.');
        } catch (ValidationException) {
            expect(GroupChatMessage::query()->count())->toBe(1);
        }
    }
});

test('personal exports identify only the members own membership announcements', function () {
    $event = Event::factory()->create();
    $member = User::factory()->withProfile()->create();
    app(RegisterForEvent::class)->handle($member, $event);
    app(RegisterForEvent::class)->handle(User::factory()->withProfile()->create(), $event);
    $export = app(BuildUserDataExport::class)->handle($member);
    expect($export['group_chat_messages'])->toHaveCount(1);
    expect($export['group_chat_messages'][0]['system_type'])->toBe('joined');
    expect($export['group_chat_messages'][0])->not->toHaveKey('author');
});
