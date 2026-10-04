<?php

use App\Actions\DeleteMember;
use App\Enums\PartnerRevisionStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\MemberMatch;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('retains a banned members messages when another participant is deleted', function () {
    Mail::fake();
    $other = User::factory()->withProfile()->create();
    $banned = User::factory()->withProfile()->create(['status' => 'banned']);
    $match = MemberMatch::query()->create(['user_low_id' => $other->id, 'user_high_id' => $banned->id]);
    $conversation = Conversation::query()->create(['match_id' => $match->id]);
    $message = $conversation->messages()->create(['author_user_id' => $banned->id, 'content' => 'Retained']);
    app(DeleteMember::class)->handle($other);
    $this->assertDatabaseHas('messages', ['id' => $message->id, 'content' => 'Retained']);
    $this->assertDatabaseHas('matches', ['id' => $match->id, 'user_low_id' => null, 'user_high_id' => $banned->id]);
    $this->assertDatabaseMissing('users', ['id' => $other->id]);
    app(DeleteMember::class)->handle($banned);
    $this->assertDatabaseMissing('messages', ['id' => $message->id]);
});

it('ignores an old purge even ten years after a ban', function () {
    $requested = now()->subDays(40)->toImmutable();
    $user = User::factory()->withProfile()->create(['status' => 'banned', 'deletion_requested_at' => $requested]);
    $this->travel(10)->years();
    (new PurgeDeletedUser($user->id, $requested->toISOString()))->handle();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'banned']);
    expect($user->fresh()->profile)->not->toBeNull();
});

it('does not purge expired partner records owned by a banned account', function () {
    $owner = User::factory()->partner()->create(['status' => 'banned']);
    $profile = PartnerProfile::factory()->create(['user_id' => $owner->id]);
    $revision = PartnerProfileRevision::factory()->for($profile)->create(['status' => PartnerRevisionStatus::Rejected, 'expires_at' => now()->subYear()]);
    $announcement = PartnerAnnouncement::factory()->for($profile)->sent()->create(['expires_at' => now()->subYear()]);
    $this->artisan('partners:purge-expired-records')->assertSuccessful();
    $this->assertDatabaseHas('partner_profile_revisions', ['id' => $revision->id]);
    $this->assertDatabaseHas('partner_announcements', ['id' => $announcement->id]);
});

it('retains a banned reaction while removing the deleted authors message', function () {
    Mail::fake();
    $author = User::factory()->withProfile()->create();
    $banned = User::factory()->withProfile()->create(['status' => 'banned']);
    $conversation = MemberMatch::query()->create(['user_low_id' => $author->id, 'user_high_id' => $banned->id])->conversation()->create();
    $message = $conversation->messages()->create(['author_user_id' => $author->id, 'content' => 'Must be removed']);
    $reaction = $message->reactions()->create(['user_id' => $banned->id]);
    app(DeleteMember::class)->handle($author);
    $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    $this->assertDatabaseHas('message_reactions', ['id' => $reaction->id, 'user_id' => $banned->id, 'message_id' => null]);
});

it('retains registrations and group messages after deleting an event organizer', function () {
    Mail::fake();
    $organizer = User::factory()->withProfile()->create();
    $banned = User::factory()->withProfile()->create(['status' => 'banned']);
    $event = Event::factory()->create(['organizer_user_id' => $organizer->id]);
    $registration = $event->registrations()->create(['user_id' => $banned->id, 'status' => 'accepted']);
    $chat = $event->chat()->create();
    $message = $chat->messages()->create(['author_user_id' => $banned->id, 'content' => 'Retained group message']);
    app(DeleteMember::class)->handle($organizer);
    $this->assertDatabaseHas('events', ['id' => $event->id, 'organizer_user_id' => null]);
    $this->assertDatabaseHas('event_registrations', ['id' => $registration->id]);
    $this->assertDatabaseHas('event_chat_messages', ['id' => $message->id]);
});
