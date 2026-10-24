<?php

use App\Enums\ProfileVisibility;
use App\Enums\RoleName;
use App\Events\GroupChatMessageReactionUpdated;
use App\Events\GroupChatMessageSent;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewGroupMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->organizer = User::factory()->withProfile()->admin()->partner()->create();
    $this->member = User::factory()->withProfile()->admin()->partner()->create();
    $this->event = Event::factory()->for($this->organizer, 'organizer')->create();
    $this->chat = GroupChat::factory()->for($this->event)->create();
    EventRegistration::factory()->accepted()->for($this->event)->for($this->member)->create();
    $this->message = GroupChatMessage::factory()->for($this->chat)->for($this->organizer, 'author')->create();
});

test('event chat delivery excludes a participant whose member eligibility was revoked', function (string $change) {
    $broadcast = unserialize(serialize(new GroupChatMessageSent($this->message)));
    $reaction = unserialize(serialize(new GroupChatMessageReactionUpdated($this->chat->id, $this->message->id, $this->organizer->id, 1, true)));
    $notification = unserialize(serialize(new NewGroupMessageNotification($this->message)));
    $this->member->load('roles');

    if ($change === 'role') {
        $this->member->roles()->detach(Role::query()->where('name', RoleName::User)->value('id'));
    } else {
        $this->member->forceFill(['email_verified_at' => null])->save();
    }

    expect(Gate::forUser($this->member)->allows('view', $this->chat))->toBeFalse()
        ->and(Gate::forUser($this->member)->allows('send', $this->chat))->toBeFalse()
        ->and(collect($broadcast->broadcastOn())->pluck('name')->all())
        ->toBe(['private-App.Models.User.'.$this->organizer->id])
        ->and(collect($reaction->broadcastOn())->pluck('name')->all())
        ->toBe(['private-App.Models.User.'.$this->organizer->id])
        ->and($notification->shouldSend($this->member, 'database'))->toBeFalse()
        ->and($notification->webPushAccessAllowed($this->member))->toBeFalse();

    $this->actingAs($this->member)->getJson(route('events.chat.show', $this->event))->assertForbidden();
})->with(['role', 'email']);

test('event chat delivery stops when the organizer loses member eligibility', function (string $change) {
    $broadcast = unserialize(serialize(new GroupChatMessageSent($this->message)));
    $notification = unserialize(serialize(new NewGroupMessageNotification($this->message)));

    if ($change === 'role') {
        $this->organizer->roles()->detach(Role::query()->where('name', RoleName::User)->value('id'));
    } else {
        $this->organizer->forceFill(['email_verified_at' => null])->save();
    }

    expect(Gate::forUser($this->member)->allows('view', $this->chat))->toBeFalse()
        ->and($broadcast->broadcastWhen())->toBeFalse()
        ->and($broadcast->broadcastOn())->toBe([])
        ->and($notification->shouldSend($this->member, 'database'))->toBeFalse()
        ->and($notification->webPushAccessAllowed($this->member))->toBeFalse();

    $this->actingAs($this->member)->getJson(route('events.chat.show', $this->event))->assertForbidden();
})->with(['role', 'email']);

test('queued event chat content stops when its participant author loses member eligibility', function (string $change) {
    $this->message->update(['author_user_id' => $this->member->id]);
    $broadcast = unserialize(serialize(new GroupChatMessageSent($this->message)));
    $reaction = unserialize(serialize(new GroupChatMessageReactionUpdated($this->chat->id, $this->message->id, $this->member->id, 1, true)));
    $notification = unserialize(serialize(new NewGroupMessageNotification($this->message)));

    if ($change === 'role') {
        $this->member->roles()->detach(Role::query()->where('name', RoleName::User)->value('id'));
    } else {
        $this->member->forceFill(['email_verified_at' => null])->save();
    }

    expect($broadcast->broadcastWhen())->toBeFalse()
        ->and($broadcast->broadcastOn())->toBe([])
        ->and($reaction->broadcastWhen())->toBeFalse()
        ->and($reaction->broadcastOn())->toBe([])
        ->and($notification->shouldSend($this->organizer, 'database'))->toBeFalse()
        ->and($notification->webPushAccessAllowed($this->organizer))->toBeFalse();
})->with(['role', 'email']);

test('event chat delivery preserves cumulative roles and hidden profiles', function (bool $hidden) {
    if ($hidden) {
        $this->organizer->profile->update(['visibility' => ProfileVisibility::Hidden]);
        $this->member->profile->update(['visibility' => ProfileVisibility::Hidden]);
    }

    expect(Gate::forUser($this->member)->allows('view', $this->chat))->toBeTrue()
        ->and(Gate::forUser($this->organizer)->allows('send', $this->chat))->toBeTrue()
        ->and(collect((new GroupChatMessageSent($this->message))->broadcastOn())->pluck('name')->all())
        ->toContain('private-App.Models.User.'.$this->organizer->id, 'private-App.Models.User.'.$this->member->id)
        ->and((new NewGroupMessageNotification($this->message))->shouldSend($this->member, 'database'))->toBeTrue();
})->with([false, true]);
