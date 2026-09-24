<?php

namespace Tests\Feature\Partner;

use App\Enums\RoleName;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerNotificationPreference;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PartnerSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_partner_role_and_guarded_partner_domain_tables(): void
    {
        $this->assertSame('partner', RoleName::Partner->value);
        $this->assertTrue(Role::query()->where('name', RoleName::Partner)->exists());
        $this->assertTrue(Schema::hasColumns('partner_profiles', [
            'user_id', 'published_revision_id', 'is_published', 'position',
        ]));
        $this->assertTrue(Schema::hasColumns('partner_profile_revisions', [
            'partner_profile_id', 'name_fr', 'name_en', 'description_fr', 'description_en',
            'image_path', 'status', 'submitted_at', 'decided_at', 'decided_by',
            'rejection_reason', 'draft_key', 'expires_at',
        ]));
        $this->assertTrue(Schema::hasColumns('partner_announcements', [
            'partner_profile_id', 'title', 'content', 'destination_url', 'status',
            'run_uuid', 'audience_prepared_at', 'sending_started_at', 'sent_at',
            'decided_by', 'decided_at', 'rejection_reason', 'expires_at',
        ]));
        $this->assertTrue(Schema::hasColumns('partner_announcement_deliveries', [
            'partner_announcement_id', 'source_announcement_id', 'announcement_title',
            'announcement_content', 'announcement_destination_url', 'user_id',
            'notification_id', 'click_token',
            'status', 'attempts', 'last_error', 'delivered_at', 'read_at',
            'dismissed_at', 'first_clicked_at', 'click_count',
        ]));
        $this->assertTrue(Schema::hasColumns('partner_announcement_metrics', [
            'partner_announcement_id', 'prepared_count', 'delivered_count', 'read_count',
            'dismissed_count', 'unique_click_count', 'total_click_count', 'expires_at',
        ]));
        $this->assertTrue(Schema::hasColumns('partner_settings', ['cooldown_days']));
        $this->assertTrue(Schema::hasColumns('partner_notification_preferences', ['user_id', 'enabled']));
        $this->assertTrue(Schema::hasColumns('role_audits', [
            'actor_user_id', 'target_user_id', 'role', 'action', 'expires_at',
        ]));
    }

    public function test_it_prevents_duplicate_partner_profiles(): void
    {
        $profile = PartnerProfile::factory()->create();

        $this->expectException(QueryException::class);

        PartnerProfile::factory()->create(['user_id' => $profile->user_id]);
    }

    public function test_it_prevents_two_mutable_drafts_for_one_partner_profile(): void
    {
        $revision = PartnerProfileRevision::factory()->create();

        $this->expectException(QueryException::class);

        PartnerProfileRevision::factory()->create([
            'partner_profile_id' => $revision->partner_profile_id,
            'draft_key' => 1,
        ]);
    }

    public function test_it_prevents_duplicate_partner_deliveries(): void
    {
        $delivery = PartnerAnnouncementDelivery::factory()->create();

        $this->expectException(QueryException::class);

        PartnerAnnouncementDelivery::factory()->create([
            'partner_announcement_id' => $delivery->partner_announcement_id,
            'user_id' => $delivery->user_id,
        ]);
    }

    public function test_it_requires_unique_delivery_click_tokens(): void
    {
        $delivery = PartnerAnnouncementDelivery::factory()->create();

        $this->expectException(QueryException::class);

        PartnerAnnouncementDelivery::factory()->create([
            'click_token' => $delivery->click_token,
        ]);
    }

    public function test_it_allows_only_one_metric_row_per_announcement(): void
    {
        $metric = PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => PartnerAnnouncementDelivery::factory()->create()->partner_announcement_id,
        ]);

        $this->expectException(QueryException::class);

        PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => $metric->partner_announcement_id,
        ]);
    }

    public function test_it_allows_only_one_notification_preference_per_user(): void
    {
        $user = User::factory()->create();
        PartnerNotificationPreference::query()->create(['user_id' => $user->id, 'enabled' => true]);

        $this->expectException(QueryException::class);

        PartnerNotificationPreference::query()->create(['user_id' => $user->id, 'enabled' => false]);
    }
}
