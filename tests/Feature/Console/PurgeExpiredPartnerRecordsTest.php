<?php

namespace Tests\Feature\Console;

use App\Actions\FinalizePartnerAnnouncement;
use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerRevisionStatus;
use App\Enums\RoleAuditAction;
use App\Enums\RoleName;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\RoleAudit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeExpiredPartnerRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.default', 's3');
        Storage::fake('s3');
        CarbonImmutable::setTestNow('2026-09-20 12:00:00');
    }

    public function test_command_purges_every_expired_record_type_in_chunks_and_is_idempotent_without_deleting_active_announcements(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $auditRows = [];
        for ($index = 0; $index < 501; $index++) {
            $auditRows[] = [
                'actor_user_id' => $actor->id,
                'target_user_id' => $target->id,
                'role' => RoleName::Partner->value,
                'action' => RoleAuditAction::Assigned->value,
                'expires_at' => now()->subSecond(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        RoleAudit::query()->insert($auditRows);
        $futureAudit = RoleAudit::query()->create([
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'role' => RoleName::User,
            'action' => RoleAuditAction::Removed,
            'expires_at' => now()->addSecond(),
        ]);

        $profile = PartnerProfile::factory()->create();
        $expiredRevision = PartnerProfileRevision::factory()->for($profile)->create([
            'status' => PartnerRevisionStatus::Rejected,
            'image_path' => 'partners/expired.webp',
            'expires_at' => now(),
        ]);
        $futureRevision = PartnerProfileRevision::factory()->for($profile)->create([
            'status' => PartnerRevisionStatus::Approved,
            'draft_key' => null,
            'image_path' => 'partners/future.webp',
            'expires_at' => now()->addDay(),
        ]);
        Storage::disk('s3')->put('partners/expired.webp', 'expired');
        Storage::disk('s3')->put('partners/future.webp', 'future');

        $expiredAnnouncement = PartnerAnnouncement::factory()->for($profile)->sent()->create([
            'expires_at' => now(),
        ]);
        $expiredMetric = PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => $expiredAnnouncement->id,
            'prepared_count' => 10,
            'expires_at' => now(),
        ]);
        $activeAnnouncement = PartnerAnnouncement::factory()->for($profile)->create([
            'status' => PartnerAnnouncementStatus::Sending,
            'expires_at' => now()->subDay(),
        ]);
        $activeMetric = PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => $activeAnnouncement->id,
            'prepared_count' => 2,
            'expires_at' => now()->subDay(),
        ]);
        $futureAnnouncement = PartnerAnnouncement::factory()->for($profile)->sent()->create([
            'expires_at' => now()->addDay(),
        ]);
        $futureMetric = PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => $futureAnnouncement->id,
            'prepared_count' => 3,
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('partners:purge-expired-records')->assertSuccessful();
        $this->artisan('partners:purge-expired-records')->assertSuccessful();

        $this->assertSame(1, RoleAudit::query()->count());
        $this->assertDatabaseHas('role_audits', ['id' => $futureAudit->id]);
        $this->assertDatabaseMissing('partner_profile_revisions', ['id' => $expiredRevision->id]);
        $this->assertDatabaseHas('partner_profile_revisions', ['id' => $futureRevision->id]);
        Storage::disk('s3')->assertMissing('partners/expired.webp');
        Storage::disk('s3')->assertExists('partners/future.webp');
        $this->assertDatabaseMissing('partner_announcements', ['id' => $expiredAnnouncement->id]);
        $this->assertDatabaseMissing('partner_announcement_metrics', ['id' => $expiredMetric->id]);
        $this->assertDatabaseHas('partner_announcements', ['id' => $activeAnnouncement->id]);
        $this->assertDatabaseHas('partner_announcement_metrics', ['id' => $activeMetric->id]);
        $this->assertDatabaseHas('partner_announcements', ['id' => $futureAnnouncement->id]);
        $this->assertDatabaseHas('partner_announcement_metrics', ['id' => $futureMetric->id]);
    }

    public function test_finalizing_an_announcement_starts_the_two_year_retention_period_for_content_and_metrics(): void
    {
        $announcement = PartnerAnnouncement::factory()->create([
            'status' => PartnerAnnouncementStatus::Sending,
            'audience_prepared_at' => now(),
            'sending_started_at' => now()->subMinute(),
        ]);
        $metric = PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => $announcement->id,
        ]);

        app(FinalizePartnerAnnouncement::class)->handle($announcement);

        $this->assertTrue($announcement->fresh()?->expires_at?->equalTo(now()->addYears(2)) ?? false);
        $this->assertTrue($metric->fresh()?->expires_at?->equalTo(now()->addYears(2)) ?? false);
    }

    public function test_scheduler_runs_the_partner_retention_command_daily_in_paris_without_overlap_on_one_server(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command ?? '', 'partners:purge-expired-records'));

        $this->assertNotNull($event);
        $this->assertSame('30 3 * * *', $event->expression);
        $this->assertSame('Europe/Paris', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }
}
