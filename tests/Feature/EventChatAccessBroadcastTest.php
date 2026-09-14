<?php

namespace Tests\Feature;

use App\Actions\BlockEventRegistrations;
use App\Actions\CancelEvent;
use App\Actions\DecideEventRegistration;
use App\Actions\RemoveEventParticipant;
use App\Actions\WithdrawFromEvent;
use App\Enums\EventRegistrationStatus;
use App\Events\EventChatAccessChanged;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class EventChatAccessBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_grants_access_while_refusal_does_not(): void
    {
        [$event, $organizer, $member, $registration] = $this->registration();
        EventFacade::fake([EventChatAccessChanged::class]);

        app(DecideEventRegistration::class)->handle($organizer, $registration, true);

        EventFacade::assertDispatched(fn (EventChatAccessChanged $change): bool =>
            $change->eventId === $event->id
            && $change->userId === $member->id
            && $change->access === 'granted');

        [, , , $refused] = $this->registration();
        app(DecideEventRegistration::class)->handle($refused->event->organizer, $refused, false);
        EventFacade::assertDispatchedTimes(EventChatAccessChanged::class, 1);
    }

    public function test_withdrawal_removal_and_blocking_revoke_accepted_access(): void
    {
        EventFacade::fake([EventChatAccessChanged::class]);

        [$withdrawEvent, , $withdrawing, $withdrawal] = $this->registration(EventRegistrationStatus::Accepted);
        app(WithdrawFromEvent::class)->handle($withdrawing, $withdrawEvent);

        [$removeEvent, $remover, $removed, $removal] = $this->registration(EventRegistrationStatus::Accepted);
        app(RemoveEventParticipant::class)->handle($remover, $removal);

        [$blockEvent, $blocker, $blocked] = $this->registration(EventRegistrationStatus::Accepted);
        app(BlockEventRegistrations::class)->handle($blocker, $blocked);

        foreach ([
            [$withdrawEvent->id, $withdrawing->id],
            [$removeEvent->id, $removed->id],
            [$blockEvent->id, $blocked->id],
        ] as [$eventId, $userId]) {
            EventFacade::assertDispatched(fn (EventChatAccessChanged $change): bool =>
                $change->eventId === $eventId
                && $change->userId === $userId
                && $change->access === 'revoked');
        }
    }

    public function test_cancellation_notifies_the_organizer_and_accepted_members_of_read_only_state(): void
    {
        [$event, $organizer, $accepted] = $this->registration(EventRegistrationStatus::Accepted);
        [, , $pending] = $this->registration(EventRegistrationStatus::Pending, $event, $organizer);
        EventFacade::fake([EventChatAccessChanged::class]);

        app(CancelEvent::class)->handle($organizer, $event);

        foreach ([$organizer->id, $accepted->id] as $userId) {
            EventFacade::assertDispatched(fn (EventChatAccessChanged $change): bool =>
                $change->eventId === $event->id
                && $change->userId === $userId
                && $change->access === 'read_only');
        }
        EventFacade::assertNotDispatched(fn (EventChatAccessChanged $change): bool =>
            $change->userId === $pending->id);
    }

    /** @return array{Event, User, User, EventRegistration} */
    private function registration(
        EventRegistrationStatus $status = EventRegistrationStatus::Pending,
        ?Event $event = null,
        ?User $organizer = null,
    ): array {
        $organizer ??= User::factory()->withProfile()->create();
        $event ??= Event::factory()->create([
            'organizer_user_id' => $organizer->id,
            'starts_at' => now()->addWeek(),
        ]);
        $member = User::factory()->withProfile()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => $status,
        ]);

        return [$event, $organizer, $member, $registration];
    }
}
