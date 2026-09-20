<?php

namespace Tests\Feature\Partner;

use App\Actions\DeleteMember;
use App\Actions\PreparePartnerAnnouncementAudience;
use App\Actions\RequestAccountDeletion;
use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Enums\PartnerRevisionStatus;
use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerNotificationPreference;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnerDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.default', 's3');
        Storage::fake('s3');
    }

    public function test_self_service_deletion_unpublishes_the_profile_cancels_active_announcements_and_skips_pending_deliveries(): void
    {
        CarbonImmutable::setTestNow('2026-09-20 12:00:00');
        Queue::fake();
        $partner = User::factory()->partner()->create();
        $profile = PartnerProfile::factory()->for($partner)->published()->create();

        $announcements = collect([
            PartnerAnnouncementStatus::Draft,
            PartnerAnnouncementStatus::PendingApproval,
            PartnerAnnouncementStatus::Approved,
            PartnerAnnouncementStatus::Sending,
        ])->mapWithKeys(fn (PartnerAnnouncementStatus $status): array => [
            $status->value => PartnerAnnouncement::factory()->for($profile)->create([
                'status' => $status,
                'audience_prepared_at' => $status === PartnerAnnouncementStatus::Sending ? null : now(),
            ]),
        ]);
        $recipient = User::factory()->create();
        PartnerNotificationPreference::query()->create(['user_id' => $recipient->id, 'enabled' => true]);
        $pending = PartnerAnnouncementDelivery::factory()
            ->for($announcements[PartnerAnnouncementStatus::Sending->value], 'announcement')
            ->for($recipient)
            ->create(['last_error' => 'do-not-export-or-retain']);
        $delivered = PartnerAnnouncementDelivery::factory()
            ->for($announcements[PartnerAnnouncementStatus::Sending->value], 'announcement')
            ->create(['status' => PartnerDeliveryStatus::Delivered]);

        app(RequestAccountDeletion::class)->handle($partner);

        $this->assertSame(UserStatus::PendingDeletion, $partner->fresh()?->status);
        $this->assertFalse($profile->fresh()?->is_published ?? true);
        foreach ($announcements as $announcement) {
            $this->assertSame(PartnerAnnouncementStatus::Cancelled, $announcement->fresh()?->status);
        }
        $this->assertSame(PartnerDeliveryStatus::Skipped, $pending->fresh()?->status);
        $this->assertNull($pending->fresh()?->last_error);
        $this->assertSame(PartnerDeliveryStatus::Delivered, $delivered->fresh()?->status);

        $before = PartnerAnnouncementDelivery::query()
            ->where('partner_announcement_id', $announcements[PartnerAnnouncementStatus::Sending->value]->id)
            ->count();
        app(PreparePartnerAnnouncementAudience::class)->handle(
            $announcements[PartnerAnnouncementStatus::Sending->value]->fresh(),
        );
        $this->assertSame($before, PartnerAnnouncementDelivery::query()
            ->where('partner_announcement_id', $announcements[PartnerAnnouncementStatus::Sending->value]->id)
            ->count());
    }

    public function test_delayed_purge_removes_recipient_data_and_orphaned_draft_images_but_preserves_aggregates_and_retained_moderation(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 12:00:00');
        $requestedAt = now()->subDays(30)->toImmutable();
        $partner = User::factory()->partner()->create([
            'status' => UserStatus::PendingDeletion,
            'deletion_requested_at' => $requestedAt,
        ]);
        PartnerNotificationPreference::query()->create(['user_id' => $partner->id, 'enabled' => true]);

        $profile = PartnerProfile::factory()->for($partner)->create();
        $retainedPath = 'partners/retained.webp';
        $orphanedPath = 'partners/orphaned.webp';
        Storage::disk('s3')->put($retainedPath, 'retained');
        Storage::disk('s3')->put($orphanedPath, 'orphaned');
        $retained = PartnerProfileRevision::factory()->for($profile)->approved()->create([
            'image_path' => $retainedPath,
            'expires_at' => now()->addYear(),
        ]);
        $draft = PartnerProfileRevision::factory()->for($profile)->create([
            'image_path' => $orphanedPath,
            'status' => PartnerRevisionStatus::Draft,
        ]);

        $ownedAnnouncement = PartnerAnnouncement::factory()->for($profile)->sent()->create();
        $metric = PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => $ownedAnnouncement->id,
            'prepared_count' => 10,
            'delivered_count' => 8,
            'read_count' => 5,
            'expires_at' => now()->addYear(),
        ]);
        $senderAnnouncement = PartnerAnnouncement::factory()->sent()->create();
        $receivedDelivery = PartnerAnnouncementDelivery::factory()
            ->for($senderAnnouncement, 'announcement')
            ->for($partner)
            ->create(['status' => PartnerDeliveryStatus::Delivered]);

        $job = new PurgeDeletedUser($partner->id, $requestedAt->toISOString());
        $job->handle();
        $job->handle();

        $this->assertDatabaseMissing('users', ['id' => $partner->id]);
        $this->assertDatabaseMissing('partner_notification_preferences', ['user_id' => $partner->id]);
        $this->assertDatabaseMissing('partner_announcement_deliveries', ['id' => $receivedDelivery->id]);
        $this->assertDatabaseMissing('partner_profile_revisions', ['id' => $draft->id]);
        $this->assertDatabaseHas('partner_profile_revisions', ['id' => $retained->id]);
        $this->assertDatabaseHas('partner_announcement_metrics', [
            'id' => $metric->id,
            'prepared_count' => 10,
            'delivered_count' => 8,
            'read_count' => 5,
        ]);
        Storage::disk('s3')->assertMissing($orphanedPath);
        Storage::disk('s3')->assertExists($retainedPath);
    }

    public function test_immediate_admin_deletion_applies_the_same_partner_cleanup_before_removing_the_user(): void
    {
        CarbonImmutable::setTestNow('2026-09-20 14:00:00');
        Mail::fake();
        $partner = User::factory()->partner()->withProfile()->create();
        PartnerNotificationPreference::query()->create(['user_id' => $partner->id, 'enabled' => true]);
        $profile = PartnerProfile::factory()->for($partner)->published()->create();
        $draft = PartnerProfileRevision::factory()->for($profile)->create([
            'image_path' => 'partners/admin-orphaned.webp',
        ]);
        Storage::disk('s3')->put((string) $draft->image_path, 'orphaned');
        $ownedAnnouncement = PartnerAnnouncement::factory()->for($profile)->create([
            'status' => PartnerAnnouncementStatus::Sending,
        ]);
        $metric = PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => $ownedAnnouncement->id,
            'prepared_count' => 4,
        ]);
        $pending = PartnerAnnouncementDelivery::factory()
            ->for($ownedAnnouncement, 'announcement')
            ->create();
        $received = PartnerAnnouncementDelivery::factory()->for($partner)->create();

        app(DeleteMember::class)->handle($partner);

        $this->assertDatabaseMissing('users', ['id' => $partner->id]);
        $this->assertDatabaseMissing('partner_notification_preferences', ['user_id' => $partner->id]);
        $this->assertDatabaseMissing('partner_announcement_deliveries', ['id' => $received->id]);
        $this->assertDatabaseMissing('partner_announcement_deliveries', ['id' => $pending->id]);
        $this->assertDatabaseHas('partner_announcement_metrics', ['id' => $metric->id, 'prepared_count' => 4]);
        $this->assertTrue($metric->fresh()?->expires_at?->equalTo(now()->addYears(2)) ?? false);
        $this->assertDatabaseMissing('partner_profile_revisions', ['id' => $draft->id]);
        Storage::disk('s3')->assertMissing('partners/admin-orphaned.webp');
    }
}
