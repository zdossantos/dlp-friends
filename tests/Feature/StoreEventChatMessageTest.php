<?php

namespace Tests\Feature;

use App\Enums\EventRegistrationStatus;
use App\Events\EventChatMessageSent;
use App\Models\Event;
use App\Models\EventChat;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreEventChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_organizer_and_an_accepted_participant_can_send_a_message_authored_from_the_session(): void
    {
        [$event, $chat, $organizer] = $this->eventChat();
        $participant = User::factory()->withProfile()->create();
        EventRegistration::factory()->accepted()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
        ]);
        EventFacade::fake([EventChatMessageSent::class]);

        foreach ([$organizer, $participant] as $index => $member) {
            $content = "Bonjour {$index}";

            $this->actingAs($member)
                ->postJson(route('events.chat.messages.store', $event), [
                    'content' => $content,
                    'author_user_id' => User::factory()->create()->id,
                ])
                ->assertCreated()
                ->assertJsonPath('data.event_chat_id', $chat->id)
                ->assertJsonPath('data.author_user_id', $member->id)
                ->assertJsonPath('data.content', $content);
        }

        $this->assertDatabaseCount('event_chat_messages', 2);
        EventFacade::assertDispatchedTimes(EventChatMessageSent::class, 2);
    }

    #[DataProvider('invalidContentProvider')]
    public function test_invalid_content_is_rejected(mixed $content): void
    {
        [$event] = $this->eventChat();

        $this->actingAs($event->organizer)
            ->postJson(route('events.chat.messages.store', $event), ['content' => $content])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->assertDatabaseEmpty('event_chat_messages');
    }

    public function test_non_members_and_non_accepted_registrations_cannot_send(): void
    {
        [$event] = $this->eventChat();

        foreach ([
            null,
            EventRegistrationStatus::Pending,
            EventRegistrationStatus::Refused,
            EventRegistrationStatus::Withdrawn,
            EventRegistrationStatus::Removed,
            EventRegistrationStatus::Blocked,
        ] as $status) {
            $user = User::factory()->withProfile()->create();

            if ($status !== null) {
                EventRegistration::factory()->create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'status' => $status,
                ]);
            }

            $this->actingAs($user)
                ->postJson(route('events.chat.messages.store', $event), ['content' => 'Interdit'])
                ->assertForbidden();
        }

        $this->assertDatabaseEmpty('event_chat_messages');
    }

    public function test_cancelled_and_seven_day_old_events_reject_new_messages(): void
    {
        foreach ([
            ['starts_at' => now()->addDay(), 'cancelled_at' => now()],
            ['starts_at' => now()->subDays(7), 'cancelled_at' => null],
        ] as $attributes) {
            [$event] = $this->eventChat($attributes);

            $this->actingAs($event->organizer)
                ->postJson(route('events.chat.messages.store', $event), ['content' => 'Trop tard'])
                ->assertForbidden();
        }

        $this->assertDatabaseEmpty('event_chat_messages');
    }

    /** @return array<string, array{mixed}> */
    public static function invalidContentProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => [" \t\n\u{00A0}"],
            'not a string' => [['message']],
            'over 2,000 characters' => [str_repeat('é', 2001)],
        ];
    }

    /** @param array<string, mixed> $attributes
     *  @return array{Event, EventChat, User}
     */
    private function eventChat(array $attributes = []): array
    {
        $organizer = User::factory()->withProfile()->create();
        $event = Event::factory()->create([
            'organizer_user_id' => $organizer->id,
            ...$attributes,
        ]);

        return [$event, EventChat::factory()->for($event)->create(), $organizer];
    }
}
