<?php

namespace Tests\Feature\Settings;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\Interest;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DirectUserDataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_contains_only_the_members_portable_data(): void
    {
        $user = User::factory()->withProfile()->create([
            'email' => 'self@example.com',
            'locale' => 'fr',
        ]);
        $other = User::factory()->withProfile()->create([
            'email' => 'other-private@example.com',
            'birth_date' => '1985-02-03',
        ]);
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret' => 'two-factor-sentinel',
            'remember_token' => 'remember-token-sentinel',
        ]);

        $activeInterest = Interest::factory()->create([
            'name' => 'Attractions',
            'name_en' => 'Rides',
            'is_active' => true,
        ]);
        $archivedInterest = Interest::factory()->create([
            'name' => 'Archives',
            'name_en' => null,
            'is_active' => false,
        ]);
        $user->profile->interestHistory()->attach([
            $activeInterest->id => ['is_selected' => true],
            $archivedInterest->id => ['is_selected' => false],
        ]);

        [$lowId, $highId] = $user->id < $other->id
            ? [$user->id, $other->id]
            : [$other->id, $user->id];
        $match = MemberMatch::factory()->create([
            'user_low_id' => $lowId,
            'user_high_id' => $highId,
        ]);
        $conversation = $match->conversation()->create();
        Message::factory()->for($conversation)->create([
            'author_user_id' => $user->id,
            'content' => 'Mon message exporté',
        ]);
        Message::factory()->for($conversation)->create([
            'author_user_id' => $other->id,
            'content' => 'Sa réponse exportée',
        ]);

        $response = $this->actingAs($user)->post(route('data-export.store'))->assertOk();
        $payload = json_decode(
            $response->streamedContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame(
            ['format_version', 'generated_at', 'account', 'profile', 'interests', 'matches', 'messages'],
            array_keys($payload),
        );
        $this->assertSame('self@example.com', $payload['account']['email']);
        $this->assertSame(['Attractions', 'Archives'], array_column($payload['interests'], 'name_fr'));
        $this->assertSame($other->profile->display_name, $payload['matches'][0]['other_member']['display_name']);
        $this->assertSame(['self'], array_column($payload['messages'], 'author'));
        $this->assertSame(['Mon message exporté'], array_column($payload['messages'], 'content'));
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('two-factor-sentinel', $json);
        $this->assertStringNotContainsString('remember-token-sentinel', $json);
        $this->assertStringNotContainsString('other-private@example.com', $json);
        $this->assertStringNotContainsString('1985-02-03', $json);
    }

    public function test_download_excludes_conversations_hidden_from_the_conversation_list(): void
    {
        $member = User::factory()->withProfile()->create();
        $visiblePeer = User::factory()->withProfile()->create();
        $hiddenPeer = User::factory()->withProfile()->create();
        $hiddenPeer->profile?->update(['visibility' => 'hidden']);
        $blocker = User::factory()->withProfile()->create();

        $visibleConversation = $this->conversationBetween($member, $visiblePeer);
        $hiddenConversation = $this->conversationBetween($member, $hiddenPeer);
        $blockedConversation = $this->conversationBetween($member, $blocker);

        Message::factory()->for($visibleConversation)->for($member, 'author')->create(['content' => 'Visible']);
        Message::factory()->for($hiddenConversation)->for($member, 'author')->create(['content' => 'Masqué']);
        Message::factory()->for($blockedConversation)->for($member, 'author')->create(['content' => 'Bloqué']);
        Block::factory()->create([
            'blocker_user_id' => $blocker->id,
            'blocked_user_id' => $member->id,
        ]);

        $response = $this->actingAs($member)->post(route('data-export.store'));
        $payload = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['Visible'], array_column($payload['messages'], 'content'));
    }

    private function conversationBetween(User $member, User $peer): Conversation
    {
        return MemberMatch::factory()->create([
            'user_low_id' => min($member->id, $peer->id),
            'user_high_id' => max($member->id, $peer->id),
        ])->conversation()->create();
    }
}
