<?php

namespace Tests\Feature;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventChat;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventChatAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('membershipProvider')]
    public function test_only_the_organizer_and_accepted_registrations_can_view(
        string $role,
        ?EventRegistrationStatus $status,
        bool $allowed,
    ): void {
        [$user, $chat] = $this->scenario($role, $status);

        $this->assertSame($allowed, Gate::forUser($user)->allows('view', $chat));
    }

    public function test_the_chat_becomes_read_only_at_cancellation_or_exactly_seven_days_after_start(): void
    {
        $this->travelTo('2026-09-14 10:00:00');
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_user_id' => $organizer->id,
            'starts_at' => now()->subDays(7)->addSecond(),
        ]);
        $chat = EventChat::factory()->for($event)->create();

        $this->assertTrue(Gate::forUser($organizer)->allows('view', $chat));
        $this->assertTrue(Gate::forUser($organizer)->allows('send', $chat));
        $this->assertFalse($chat->isReadOnly());

        $this->travel(1)->second();
        $chat->refresh();

        $this->assertTrue(Gate::forUser($organizer)->allows('view', $chat));
        $this->assertFalse(Gate::forUser($organizer)->allows('send', $chat));
        $this->assertTrue($chat->isReadOnly());

        $event->update([
            'starts_at' => now()->addDay(),
            'cancelled_at' => now(),
        ]);
        $chat->refresh();

        $this->assertTrue(Gate::forUser($organizer)->allows('view', $chat));
        $this->assertFalse(Gate::forUser($organizer)->allows('send', $chat));
        $this->assertTrue($chat->isReadOnly());
    }

    /** @return array<string, array{string, EventRegistrationStatus|null, bool}> */
    public static function membershipProvider(): array
    {
        return [
            'organizer' => ['organizer', null, true],
            'accepted' => ['participant', EventRegistrationStatus::Accepted, true],
            'pending' => ['participant', EventRegistrationStatus::Pending, false],
            'refused' => ['participant', EventRegistrationStatus::Refused, false],
            'withdrawn' => ['participant', EventRegistrationStatus::Withdrawn, false],
            'removed' => ['participant', EventRegistrationStatus::Removed, false],
            'blocked' => ['participant', EventRegistrationStatus::Blocked, false],
            'stranger' => ['stranger', null, false],
        ];
    }

    /** @return array{User, EventChat} */
    private function scenario(string $role, ?EventRegistrationStatus $status): array
    {
        $organizer = User::factory()->create();
        $event = Event::factory()->create(['organizer_user_id' => $organizer->id]);
        $chat = EventChat::factory()->for($event)->create();

        if ($role === 'organizer') {
            return [$organizer, $chat];
        }

        $user = User::factory()->create();

        if ($status !== null) {
            EventRegistration::factory()->create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => $status,
            ]);
        }

        return [$user, $chat];
    }
}
