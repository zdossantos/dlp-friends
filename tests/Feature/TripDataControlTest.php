<?php

use App\Actions\BuildUserDataExport;
use App\Actions\DeleteMember;
use App\Jobs\PurgeDeletedUser;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripDepartureReport;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);
beforeEach(fn () => Mail::fake());

test('trip export contains only own routes registrations departure reasons messages and submitted reports', function () {
    $owner = User::factory()->withProfile()->create();
    $other = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($owner, 'organizer')->create(['meeting_point' => 'OWN-MEETING']);
    $otherTrip = Trip::factory()->for($other, 'organizer')->create(['meeting_point' => 'OTHER-MEETING']);
    $registration = TripRegistration::factory()->for($owner)->for($otherTrip)->create(['status' => 'withdrawn', 'reason' => 'OWN-REASON']);
    TripRegistration::factory()->for($other)->for($trip)->create(['reason' => 'OTHER-REASON']);
    $departure = TripDeparture::factory()->for($otherTrip)->create(['trip_registration_id' => $registration->id, 'user_id' => $owner->id, 'reason' => 'OWN-DEPARTURE']);
    $chat = $trip->chat()->create();
    $chat->messages()->create(['author_user_id' => $owner->id, 'content' => 'OWN-MESSAGE']);
    $chat->messages()->create(['author_user_id' => $other->id, 'content' => 'OTHER-MESSAGE']);
    TripDepartureReport::factory()->create(['reporter_user_id' => $owner->id, 'target_user_id' => $other->id, 'details' => 'OWN-REPORT']);
    TripDepartureReport::factory()->create(['reporter_user_id' => $other->id, 'target_user_id' => $owner->id, 'details' => 'RECEIVED-REPORT']);
    $data = app(BuildUserDataExport::class)->handle($owner);
    expect($data['organized_trips'])->toHaveCount(1)->and($data['organized_trips'][0]['meeting_point'])->toBe('OWN-MEETING')
        ->and($data['trip_registrations'])->toHaveCount(1)->and($data['trip_registrations'][0]['reason'])->toBe('OWN-REASON')
        ->and($data['trip_departures'])->toHaveCount(1)->and($data['trip_departures'][0]['reason'])->toBe('OWN-DEPARTURE')
        ->and($data['moderation']['trip_reports_submitted'])->toHaveCount(1)->and($data['moderation']['trip_reports_submitted'][0]['details'])->toBe('OWN-REPORT')
        ->and($data['notification_preferences']['trips'])->toBeTrue();
    $json = json_encode($data);
    expect($json)->toContain('OWN-MESSAGE')->not->toContain('OTHER-MEETING', 'OTHER-REASON', 'OTHER-MESSAGE', 'RECEIVED-REPORT', $other->email, 'reporter_user_id', 'decided_by_user_id');
});

test('account purge removes normal trip data and own registrations without removing another organizers trip', function () {
    $requested = now()->startOfSecond()->subDays(40)->toImmutable();
    $owner = User::factory()->withProfile()->create(['status' => 'pending_deletion', 'deletion_requested_at' => $requested]);
    $own = Trip::factory()->for($owner, 'organizer')->create();
    $own->chat()->create();
    $other = Trip::factory()->create();
    $registration = TripRegistration::factory()->for($other)->for($owner)->create(['status' => 'withdrawn']);
    $departure = TripDeparture::factory()->for($other)->create(['trip_registration_id' => $registration->id, 'user_id' => $owner->id]);
    $report = TripDepartureReport::factory()->create(['trip_departure_id' => $departure->id, 'reporter_user_id' => $other->organizer_user_id, 'target_user_id' => $owner->id]);
    (new PurgeDeletedUser($owner->id, $requested->toISOString()))->handle();
    expect($owner->fresh())->toBeNull()->and($own->fresh())->toBeNull()->and($other->fresh())->not->toBeNull()->and($registration->fresh())->toBeNull()->and($departure->fresh())->toBeNull()->and($report->fresh())->toBeNull();
});

test('deleting a trip organizer preserves banned registrations chat reactions and departure reports', function () {
    $owner = User::factory()->withProfile()->create();
    $banned = User::factory()->withProfile()->create(['status' => 'banned']);
    $trip = Trip::factory()->for($owner, 'organizer')->create();
    $registration = TripRegistration::factory()->for($trip)->for($banned)->create(['status' => 'withdrawn']);
    $departure = TripDeparture::factory()->for($trip)->create(['trip_registration_id' => $registration->id, 'user_id' => $banned->id]);
    $report = TripDepartureReport::factory()->create(['trip_departure_id' => $departure->id, 'reporter_user_id' => $owner->id, 'target_user_id' => $banned->id]);
    $chat = $trip->chat()->create();
    $message = $chat->messages()->create(['author_user_id' => $banned->id, 'content' => 'FROZEN-MESSAGE']);
    $ownMessage = $chat->messages()->create(['author_user_id' => $owner->id, 'content' => 'DELETE-MESSAGE']);
    $reaction = $ownMessage->reactions()->create(['user_id' => $banned->id]);
    app(DeleteMember::class)->handle($owner);
    expect($trip->fresh()->organizer_user_id)->toBeNull()->and($trip->fresh()->cancelled_at)->not->toBeNull()
        ->and($registration->fresh())->not->toBeNull()->and($departure->fresh())->not->toBeNull()->and($report->fresh()->reporter_user_id)->toBeNull()
        ->and($message->fresh()->content)->toBe('FROZEN-MESSAGE')->and($ownMessage->fresh())->toBeNull()->and($reaction->fresh()->group_chat_message_id)->toBeNull();
});

test('deleting a passenger preserves their detached registration and report needed by a banned organizer', function () {
    $owner = User::factory()->withProfile()->create();
    $banned = User::factory()->withProfile()->create(['status' => 'banned']);
    $trip = Trip::factory()->for($banned, 'organizer')->create();
    $registration = TripRegistration::factory()->for($trip)->for($owner)->create(['status' => 'withdrawn', 'reason' => 'FROZEN-REASON']);
    $departure = TripDeparture::factory()->for($trip)->create(['trip_registration_id' => $registration->id, 'user_id' => $owner->id]);
    $report = TripDepartureReport::factory()->create(['trip_departure_id' => $departure->id, 'reporter_user_id' => $banned->id, 'target_user_id' => $owner->id]);
    app(DeleteMember::class)->handle($owner);
    expect($registration->fresh()->user_id)->toBeNull()->and($registration->fresh()->reason)->toBe('FROZEN-REASON')->and($departure->fresh()->user_id)->toBeNull()->and($report->fresh()->target_user_id)->toBeNull();
});

test('a queued account purge cannot erase trips after the account is banned', function () {
    $requested = now()->startOfSecond()->subDays(40)->toImmutable();
    $owner = User::factory()->withProfile()->create(['status' => 'pending_deletion', 'deletion_requested_at' => $requested]);
    $trip = Trip::factory()->for($owner, 'organizer')->create();
    $job = new PurgeDeletedUser($owner->id, $requested->toISOString());
    $owner->forceFill(['status' => 'banned'])->save();
    $job->handle();
    expect($owner->fresh())->not->toBeNull()->and($trip->fresh())->not->toBeNull();
});
