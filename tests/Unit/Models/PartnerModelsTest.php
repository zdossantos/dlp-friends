<?php

namespace Tests\Unit\Models;

use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Enums\PartnerRevisionStatus;
use App\Enums\RoleAuditAction;
use App\Enums\RoleName;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerNotificationPreference;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\PartnerSetting;
use App\Models\RoleAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_models_expose_their_relations_and_enum_casts(): void
    {
        $partner = User::factory()->partner()->create();
        $profile = PartnerProfile::factory()->for($partner)->create();
        $revision = PartnerProfileRevision::factory()->for($profile)->approved()->create();
        $profile->update(['published_revision_id' => $revision->id, 'is_published' => true]);
        $announcement = PartnerAnnouncement::factory()->for($profile)->approved()->create();
        $recipient = User::factory()->create();
        $delivery = PartnerAnnouncementDelivery::factory()
            ->for($announcement, 'announcement')
            ->for($recipient)
            ->create();
        $metric = PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => $announcement->id,
        ]);
        $preference = PartnerNotificationPreference::query()->create([
            'user_id' => $partner->id,
            'enabled' => true,
        ]);
        $audit = RoleAudit::query()->create([
            'actor_user_id' => $partner->id,
            'target_user_id' => $recipient->id,
            'role' => RoleName::Partner,
            'action' => RoleAuditAction::Assigned,
        ]);

        $this->assertTrue($profile->user->is($partner));
        $this->assertTrue($partner->partnerProfile->is($profile));
        $this->assertTrue($profile->revisions->contains($revision));
        $this->assertTrue($profile->fresh()->publishedRevision->is($revision));
        $this->assertTrue($revision->partnerProfile->is($profile));
        $this->assertTrue($profile->announcements->contains($announcement));
        $this->assertTrue($announcement->partnerProfile->is($profile));
        $this->assertTrue($announcement->deliveries->contains($delivery));
        $this->assertTrue($announcement->metric->is($metric));
        $this->assertTrue($delivery->announcement->is($announcement));
        $this->assertTrue($delivery->user->is($recipient));
        $this->assertTrue($metric->announcement->is($announcement));
        $this->assertTrue($preference->user->is($partner));
        $this->assertTrue($partner->partnerNotificationPreference->is($preference));
        $this->assertTrue($recipient->partnerAnnouncementDeliveries->contains($delivery));
        $this->assertTrue($audit->actor->is($partner));
        $this->assertTrue($audit->target->is($delivery->user));
        $this->assertTrue($partner->roleAuditsAsActor->contains($audit));
        $this->assertTrue($recipient->roleAuditsAsTarget->contains($audit));
        $this->assertSame(PartnerRevisionStatus::Approved, $revision->status);
        $this->assertSame(PartnerAnnouncementStatus::Approved, $announcement->status);
        $this->assertSame(PartnerDeliveryStatus::Pending, $delivery->status);
        $this->assertSame(RoleName::Partner, $audit->role);
        $this->assertSame(RoleAuditAction::Assigned, $audit->action);
    }

    public function test_published_and_owned_scopes_filter_partner_records(): void
    {
        $partner = User::factory()->partner()->create();
        $otherPartner = User::factory()->partner()->create();
        $published = PartnerProfile::factory()->for($partner)->published()->create();
        PartnerProfile::factory()->for($otherPartner)->create();
        $owned = PartnerAnnouncement::factory()->for($published)->create();
        PartnerAnnouncement::factory()->create();

        $this->assertTrue(PartnerProfile::query()->published()->sole()->is($published));
        $this->assertTrue(PartnerAnnouncement::query()->ownedBy($partner)->sole()->is($owned));
    }

    public function test_notification_preferences_are_disabled_until_explicitly_enabled(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(PartnerNotificationPreference::enabledFor($user));

        $user->partnerNotificationPreference()->create(['enabled' => true]);

        $this->assertTrue(PartnerNotificationPreference::enabledFor($user));
    }

    public function test_partner_setting_current_returns_one_default_singleton(): void
    {
        $first = PartnerSetting::current();
        $second = PartnerSetting::current();

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $first->id);
        $this->assertSame(30, $first->cooldown_days);
        $this->assertSame(1, PartnerSetting::query()->count());
    }
}
