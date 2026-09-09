<?php

namespace Tests\Feature;

use App\Actions\DecideEventRegistration;
use App\Actions\RegisterForEvent;
use App\Actions\RemoveEventParticipant;
use App\Actions\WithdrawFromEvent;
use App\Enums\EventRegistrationMode;
use App\Enums\EventRegistrationStatus;
use App\Models\Block;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_registration_accepts_until_capacity_without_waitlist_promotion(): void
    {
        $event = Event::factory()->create(['capacity' => 2]);
        $member = User::factory()->withProfile()->create();
        $other = User::factory()->withProfile()->create();

        $registration = app(RegisterForEvent::class)->handle($member, $event);

        $this->assertSame(EventRegistrationStatus::Accepted, $registration->status);
        $this->expectException(ValidationException::class);
        app(RegisterForEvent::class)->handle($other, $event);
    }

    public function test_manual_registration_can_be_accepted_or_refused_only_by_the_organizer(): void
    {
        $event = Event::factory()->create([
            'capacity' => 2,
            'registration_mode' => EventRegistrationMode::Manual,
        ]);
        $accepted = app(RegisterForEvent::class)->handle(User::factory()->withProfile()->create(), $event);
        $refused = app(RegisterForEvent::class)->handle(User::factory()->withProfile()->create(), $event);

        $this->assertSame(EventRegistrationStatus::Pending, $accepted->status);
        app(DecideEventRegistration::class)->handle($event->organizer, $accepted, true);
        app(DecideEventRegistration::class)->handle($event->organizer, $refused, false);

        $this->assertSame(EventRegistrationStatus::Accepted, $accepted->refresh()->status);
        $this->assertSame(EventRegistrationStatus::Refused, $refused->refresh()->status);

        $another = app(RegisterForEvent::class)->handle(User::factory()->withProfile()->create(), $event);
        $this->expectException(ValidationException::class);
        app(DecideEventRegistration::class)->handle(User::factory()->create(), $another, true);
    }

    public function test_a_member_can_withdraw_before_start_and_only_withdrawn_members_can_register_again(): void
    {
        $event = Event::factory()->create();
        $member = User::factory()->withProfile()->create();
        $registration = app(RegisterForEvent::class)->handle($member, $event);

        app(WithdrawFromEvent::class)->handle($member, $event);
        $this->assertSame(EventRegistrationStatus::Withdrawn, $registration->refresh()->status);
        $this->assertSame(
            EventRegistrationStatus::Accepted,
            app(RegisterForEvent::class)->handle($member, $event)->status,
        );

        $registration->update(['status' => EventRegistrationStatus::Refused]);
        $this->expectException(ValidationException::class);
        app(RegisterForEvent::class)->handle($member, $event);
    }

    public function test_an_organizer_can_remove_a_participant_permanently(): void
    {
        $registration = EventRegistration::factory()->accepted()->create();

        app(RemoveEventParticipant::class)->handle($registration->event->organizer, $registration);

        $this->assertSame(EventRegistrationStatus::Removed, $registration->refresh()->status);
        $this->expectException(ValidationException::class);
        app(RegisterForEvent::class)->handle($registration->user, $registration->event);
    }

    public function test_registration_rejects_started_cancelled_blocked_and_duplicate_requests(): void
    {
        $member = User::factory()->withProfile()->create();
        $event = Event::factory()->create();
        app(RegisterForEvent::class)->handle($member, $event);

        foreach ([
            $event,
            Event::factory()->create(['starts_at' => now()]),
            Event::factory()->create(['cancelled_at' => now()]),
        ] as $unavailable) {
            try {
                app(RegisterForEvent::class)->handle($member, $unavailable);
                $this->fail('The unavailable registration should fail.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $blockedEvent = Event::factory()->create();
        Block::factory()->create([
            'blocker_user_id' => $member->id,
            'blocked_user_id' => $blockedEvent->organizer_user_id,
        ]);
        $this->expectException(ValidationException::class);
        app(RegisterForEvent::class)->handle($member, $blockedEvent);
    }

    public function test_registration_http_routes_apply_the_transitions(): void
    {
        $event = Event::factory()->create(['registration_mode' => EventRegistrationMode::Manual]);
        $member = User::factory()->withProfile()->create();

        $this->actingAs($member)->post(route('events.registrations.store', $event))->assertRedirect();
        $registration = EventRegistration::query()->firstOrFail();
        $this->actingAs($event->organizer)->patch(route('events.registrations.decision', $registration), [
            'accept' => true,
        ])->assertRedirect();
        $this->actingAs($member)->delete(route('events.registrations.destroy', $event))->assertRedirect();

        $this->assertSame(EventRegistrationStatus::Withdrawn, $registration->refresh()->status);
    }
}
