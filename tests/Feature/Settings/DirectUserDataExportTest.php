<?php

namespace Tests\Feature\Settings;

use App\Actions\BuildUserDataExport;
use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Enums\PartnerRevisionStatus;
use App\Enums\RoleAuditAction;
use App\Enums\RoleName;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventChat;
use App\Models\EventChatMessage;
use App\Models\EventRegistration;
use App\Models\Interest;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerNotificationPreference;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\RoleAudit;
use App\Models\User;
use Carbon\CarbonImmutable;
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
        $organizedEvent = Event::factory()->create([
            'organizer_user_id' => $user->id,
            'detailed_location' => 'Mon lieu privé',
        ]);
        $eventChat = EventChat::factory()->for($organizedEvent)->create();
        EventChatMessage::factory()->for($eventChat)->for($user, 'author')->create(['content' => 'Mon message de groupe']);
        EventChatMessage::factory()->for($eventChat)->for($other, 'author')->create(['content' => 'Message du groupe par un autre']);
        EventRegistration::factory()->accepted()->create(['event_id' => $organizedEvent->id]);
        $ownRegistration = EventRegistration::factory()->accepted()->create(['user_id' => $user->id]);
        $user->notifications()->create([
            'id' => fake()->uuid(),
            'type' => 'event-test',
            'data' => [
                'category' => 'events',
                'translation_key' => 'notifications.items.event_changed',
                'parameters' => ['event' => $ownRegistration->event->title],
                'target_type' => 'event',
                'target_id' => $ownRegistration->event_id,
            ],
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
            ['format_version', 'generated_at', 'account', 'profile', 'interests', 'matches', 'messages', 'event_chat_messages', 'organized_events', 'event_registrations', 'notifications', 'notification_preferences', 'role_history', 'partner_profile', 'partner_profile_revisions', 'partner_announcements', 'received_partner_announcements'],
            array_keys($payload),
        );
        $this->assertSame('self@example.com', $payload['account']['email']);
        $this->assertSame(['Attractions', 'Archives'], array_column($payload['interests'], 'name_fr'));
        $this->assertSame($other->profile->display_name, $payload['matches'][0]['other_member']['display_name']);
        $this->assertSame(['self'], array_column($payload['messages'], 'author'));
        $this->assertSame(['Mon message exporté'], array_column($payload['messages'], 'content'));
        $this->assertSame(['Mon message de groupe'], array_column($payload['event_chat_messages'], 'content'));
        $this->assertSame([$organizedEvent->id], array_column($payload['organized_events'], 'id'));
        $this->assertSame([$ownRegistration->event_id], array_column($payload['event_registrations'], 'event_id'));
        $this->assertSame('events', $payload['notifications'][0]['category']);
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('two-factor-sentinel', $json);
        $this->assertStringNotContainsString('remember-token-sentinel', $json);
        $this->assertStringNotContainsString('other-private@example.com', $json);
        $this->assertStringNotContainsString('1985-02-03', $json);
        $this->assertStringNotContainsString($organizedEvent->registrations->first()->user->profile->display_name, $json);
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

    public function test_export_contains_every_owned_partner_record_in_primary_key_order_without_other_members_or_operational_secrets(): void
    {
        CarbonImmutable::setTestNow('2026-09-20 10:15:30');
        $partner = User::factory()->partner()->create(['email' => 'partner@example.test']);
        $admin = User::factory()->admin()->create(['email' => 'actor-private@example.test']);
        $recipient = User::factory()->create(['email' => 'recipient-private@example.test']);
        $otherPartner = User::factory()->partner()->create(['email' => 'sender-private@example.test']);

        $preference = PartnerNotificationPreference::query()->create([
            'user_id' => $partner->id,
            'enabled' => true,
        ]);
        $preference->forceFill(['updated_at' => now()->subHour()])->save();

        $profile = PartnerProfile::factory()->for($partner)->create([
            'is_published' => true,
            'position' => 7,
        ]);
        $firstRevision = PartnerProfileRevision::factory()->for($profile)->create([
            'name_fr' => 'Partenaire FR',
            'name_en' => 'Partner EN',
            'description_fr' => 'Description FR',
            'description_en' => 'Description EN',
            'image_path' => 'partners/private-first.webp',
            'status' => PartnerRevisionStatus::Approved,
            'submitted_at' => now()->subDays(4),
            'decided_at' => now()->subDays(3),
            'decided_by' => $admin->id,
            'draft_key' => null,
            'expires_at' => now()->addYears(2),
        ]);
        $secondRevision = PartnerProfileRevision::factory()->for($profile)->create([
            'name_fr' => 'Brouillon FR',
            'name_en' => 'Draft EN',
            'description_fr' => 'Brouillon description FR',
            'description_en' => 'Draft description EN',
            'image_path' => 'partners/private-second.webp',
            'status' => PartnerRevisionStatus::Draft,
        ]);
        $profile->update(['published_revision_id' => $firstRevision->id]);

        $firstAnnouncement = PartnerAnnouncement::factory()->for($profile)->sent()->create([
            'title' => 'Première annonce',
            'content' => 'Contenu partenaire',
            'destination_url' => 'https://example.test/offer',
            'run_uuid' => '11111111-1111-4111-8111-111111111111',
            'decided_by' => $admin->id,
            'expires_at' => now()->addYears(2),
        ]);
        $metric = PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => $firstAnnouncement->id,
            'prepared_count' => 5,
            'delivered_count' => 4,
            'read_count' => 3,
            'dismissed_count' => 2,
            'unique_click_count' => 1,
            'total_click_count' => 6,
            'expires_at' => now()->addYears(2),
        ]);
        $secondAnnouncement = PartnerAnnouncement::factory()->for($profile)->create([
            'title' => 'Deuxième annonce',
            'status' => PartnerAnnouncementStatus::Draft,
        ]);
        PartnerAnnouncementDelivery::factory()
            ->for($firstAnnouncement, 'announcement')
            ->for($recipient)
            ->create([
                'status' => PartnerDeliveryStatus::Failed,
                'click_token' => str_repeat('a', 64),
                'last_error' => 'private-delivery-error',
            ]);

        $senderProfile = PartnerProfile::factory()->for($otherPartner)->create();
        $receivedAnnouncement = PartnerAnnouncement::factory()->for($senderProfile)->sent()->create([
            'title' => 'Annonce reçue',
            'content' => 'Contenu reçu',
            'destination_url' => 'https://sender.example.test/news',
        ]);
        $received = PartnerAnnouncementDelivery::factory()
            ->for($receivedAnnouncement, 'announcement')
            ->for($partner)
            ->create([
                'status' => PartnerDeliveryStatus::Delivered,
                'click_token' => str_repeat('b', 64),
                'last_error' => 'received-private-error',
                'delivered_at' => now()->subHours(5),
                'read_at' => now()->subHours(4),
                'dismissed_at' => now()->subHours(3),
                'first_clicked_at' => now()->subHours(2),
                'click_count' => 2,
            ]);

        $firstAudit = RoleAudit::query()->create([
            'actor_user_id' => $admin->id,
            'target_user_id' => $partner->id,
            'role' => RoleName::Partner,
            'action' => RoleAuditAction::Assigned,
            'expires_at' => now()->addYears(2),
        ]);
        $secondAudit = RoleAudit::query()->create([
            'actor_user_id' => null,
            'target_user_id' => $partner->id,
            'role' => RoleName::User,
            'action' => RoleAuditAction::Removed,
            'expires_at' => now()->addYears(2),
        ]);
        RoleAudit::query()->create([
            'actor_user_id' => $partner->id,
            'target_user_id' => $recipient->id,
            'role' => RoleName::Partner,
            'action' => RoleAuditAction::Assigned,
            'expires_at' => now()->addYears(2),
        ]);

        $payload = app(BuildUserDataExport::class)->handle($partner);

        $this->assertSame([
            'partner_announcements' => true,
            'updated_at' => $preference->updated_at?->toIso8601String(),
        ], $payload['notification_preferences']);
        $this->assertSame([$firstAudit->id, $secondAudit->id], array_column($payload['role_history'], 'id'));
        $this->assertSame(['administrator', 'system'], array_column($payload['role_history'], 'actor'));
        $this->assertSame($profile->id, $payload['partner_profile']['id']);
        $this->assertSame([$firstRevision->id, $secondRevision->id], array_column($payload['partner_profile_revisions'], 'id'));
        $this->assertSame(['approved', 'draft'], array_column($payload['partner_profile_revisions'], 'status'));
        $this->assertSame([$firstAnnouncement->id, $secondAnnouncement->id], array_column($payload['partner_announcements'], 'id'));
        $this->assertSame(6, $payload['partner_announcements'][0]['metrics']['total_click_count']);
        $this->assertSame([
            'id', 'announcement_id', 'title', 'content', 'destination_url', 'status',
            'delivered_at', 'read_at', 'dismissed_at', 'first_clicked_at', 'click_count',
            'created_at', 'updated_at',
        ], array_keys($payload['received_partner_announcements'][0]));
        $this->assertSame($received->id, $payload['received_partner_announcements'][0]['id']);
        $this->assertSame('delivered', $payload['received_partner_announcements'][0]['status']);
        $this->assertSame($received->read_at?->toIso8601String(), $payload['received_partner_announcements'][0]['read_at']);
        $this->assertSame($metric->expires_at?->toIso8601String(), $payload['partner_announcements'][0]['metrics']['expires_at']);

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        foreach ([
            'actor-private@example.test',
            'recipient-private@example.test',
            'sender-private@example.test',
            'partners/private-first.webp',
            'partners/private-second.webp',
            str_repeat('a', 64),
            str_repeat('b', 64),
            'private-delivery-error',
            'received-private-error',
            '11111111-1111-4111-8111-111111111111',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertArrayNotHasKey('decided_by', $payload['partner_profile_revisions'][0]);
        $this->assertArrayNotHasKey('user_id', $payload['received_partner_announcements'][0]);
        $this->assertArrayNotHasKey('notification_id', $payload['received_partner_announcements'][0]);
        $this->assertArrayNotHasKey('click_token', $payload['received_partner_announcements'][0]);
        $this->assertArrayNotHasKey('last_error', $payload['received_partner_announcements'][0]);
    }

    private function conversationBetween(User $member, User $peer): Conversation
    {
        return MemberMatch::factory()->create([
            'user_low_id' => min($member->id, $peer->id),
            'user_high_id' => max($member->id, $peer->id),
        ])->conversation()->create();
    }
}
