<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationStarter;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\ConversationStarterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConversationStarterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_contains_the_eighteen_approved_localized_starters(): void
    {
        $this->seed(ConversationStarterSeeder::class);

        expect(ConversationStarter::query()->count())->toBe(18)
            ->and(ConversationStarter::query()->where('is_active', true)->count())->toBe(18)
            ->and(ConversationStarter::query()->orderBy('sort_order')->first()?->text_fr)
            ->toBe('C’est quoi ton attraction préférée à Disneyland Paris ?')
            ->and(ConversationStarter::query()->orderBy('sort_order')->first()?->text_en)
            ->toBe('What is your favorite attraction at Disneyland Paris?');
    }

    public function test_an_empty_conversation_exposes_three_distinct_active_starters_in_the_member_locale(): void
    {
        [$member, $conversation] = $this->conversation();
        ConversationStarter::factory()->count(15)->create();
        ConversationStarter::factory()->inactive()->create();

        $this->actingAs($member)
            ->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page
                ->has('conversationStarters', 3)
                ->where('conversationStarters', fn ($starters): bool => $starters->pluck('id')->unique()->count() === 3
                    && $starters->every(fn (array $starter): bool => str_starts_with($starter['text'], 'FR '))));
    }

    public function test_an_english_member_receives_english_starter_copy(): void
    {
        [$member, $conversation] = $this->conversation(locale: 'en');
        ConversationStarter::factory()->count(3)->create();

        $this->actingAs($member)
            ->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversationStarters', fn ($starters): bool => $starters->every(
                    fn (array $starter): bool => str_starts_with($starter['text'], 'EN '),
                )));
    }

    public function test_starters_are_not_exposed_after_the_first_message(): void
    {
        [$member, $conversation] = $this->conversation();
        ConversationStarter::factory()->count(3)->create();
        Message::factory()->for($conversation)->for($member, 'author')->create();

        $this->actingAs($member)
            ->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page->where('conversationStarters', []));

        $this->actingAs($member)
            ->get(route('conversations.show', $conversation).'?messages_before=0')
            ->assertInertia(fn (Assert $page) => $page->where('conversationStarters', []));
    }

    /** @return array{User, Conversation} */
    private function conversation(string $locale = 'fr'): array
    {
        $first = User::factory()->withProfile()->create(['locale' => $locale]);
        $second = User::factory()->withProfile()->create();
        [$low, $high] = $first->id < $second->id ? [$first, $second] : [$second, $first];
        $match = MemberMatch::factory()->create([
            'user_low_id' => $low->id,
            'user_high_id' => $high->id,
        ]);

        return [$first, $match->conversation()->create()];
    }
}
