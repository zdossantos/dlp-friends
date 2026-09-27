<?php

namespace Tests\Feature\Settings;

use App\Actions\UpdatePartnerNotificationPreference;
use App\Enums\RoleName;
use App\Enums\WebPushPreference;
use App\Models\Role;
use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerNotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_announcements_are_enabled_by_default_without_persisting_a_preference(): void
    {
        $member = User::factory()->withProfile()->create();

        $this->actingAs($member)
            ->get(route('notification-preferences.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Notifications')
                ->where('partnerAnnouncementsEnabled', true)
                ->where('auth.user.roles', [['name' => RoleName::User->value]]));

        $this->assertDatabaseMissing('partner_notification_preferences', [
            'user_id' => $member->id,
        ]);
    }

    public function test_members_do_not_receive_the_administration_preference(): void
    {
        $member = User::factory()->withProfile()->create();

        $this->actingAs($member)
            ->get(route('notification-preferences.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('preferences.administration'));
    }

    public function test_partners_do_not_receive_the_administration_preference(): void
    {
        $partner = User::factory()->partnerOnly()->create();

        $this->actingAs($partner)
            ->get(route('notification-preferences.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('preferences.administration'));
    }

    public function test_administrators_receive_the_administration_preference(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('notification-preferences.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preferences.administration', true));
    }

    public function test_members_cannot_force_an_administration_preference_update(): void
    {
        $member = User::factory()->withProfile()->create();
        $member->notificationPreferences()->create([
            'category' => WebPushPreference::Administration,
            'enabled' => false,
        ]);

        $this->actingAs($member)
            ->patch(route('notification-preferences.update'), [
                'partner_announcements' => true,
                'administration' => true,
            ])
            ->assertRedirect(route('notification-preferences.edit'));

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $member->id,
            'category' => WebPushPreference::Administration->value,
            'enabled' => false,
        ]);
    }

    public function test_partner_announcement_consent_is_stored_independently_and_can_be_withdrawn(): void
    {
        $member = User::factory()->withProfile()->create(['show_presence' => true]);
        $notification = $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'category' => 'events',
                'translation_key' => 'notifications.items.event_changed',
                'parameters' => [],
            ],
        ]);

        $this->actingAs($member)
            ->patch(route('notification-preferences.update'), [
                'partner_announcements' => true,
            ])
            ->assertRedirect(route('notification-preferences.edit'));

        $this->assertDatabaseHas('partner_notification_preferences', [
            'user_id' => $member->id,
            'enabled' => true,
        ]);

        $this->actingAs($member)
            ->patch(route('notification-preferences.update'), [
                'partner_announcements' => false,
            ])
            ->assertRedirect(route('notification-preferences.edit'));

        $this->assertDatabaseHas('partner_notification_preferences', [
            'user_id' => $member->id,
            'enabled' => false,
        ]);
        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
        $this->assertTrue($member->fresh()->show_presence);
    }

    public function test_the_consent_action_updates_the_preference_through_the_user_mutex(): void
    {
        $member = User::factory()->create();

        app(UpdatePartnerNotificationPreference::class)->handle($member, true);
        app(UpdatePartnerNotificationPreference::class)->handle($member, false);

        $this->assertDatabaseHas('partner_notification_preferences', [
            'user_id' => $member->id,
            'enabled' => false,
        ]);
    }

    public function test_partner_only_accounts_can_manage_notification_settings(): void
    {
        $partner = User::factory()->partnerOnly()->create();

        $this->actingAs($partner)
            ->get(route('notification-preferences.edit'))
            ->assertOk();

        $this->actingAs($partner)
            ->patch(route('notification-preferences.update'), [
                'partner_announcements' => true,
            ])
            ->assertRedirect(route('notification-preferences.edit'));

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $partner->id,
            'category' => WebPushPreference::PartnerAnnouncements->value,
            'enabled' => true,
        ]);
    }

    public function test_partner_announcement_consent_must_be_an_explicit_boolean(): void
    {
        $member = User::factory()->withProfile()->create();

        $this->actingAs($member)
            ->from(route('notification-preferences.edit'))
            ->patch(route('notification-preferences.update'), [])
            ->assertRedirect(route('notification-preferences.edit'))
            ->assertSessionHasErrors('partner_announcements');

        $this->assertDatabaseMissing('partner_notification_preferences', [
            'user_id' => $member->id,
        ]);
    }

    public function test_disabling_every_notification_revokes_devices_and_all_categories(): void
    {
        $member = User::factory()->withProfile()->create();
        WebPushSubscription::factory()->count(2)->for($member)->create();

        $this->actingAs($member)
            ->delete(route('notification-preferences.disable-all'))
            ->assertRedirect(route('notification-preferences.edit'));

        foreach ([
            WebPushPreference::Messages,
            WebPushPreference::Matches,
            WebPushPreference::Events,
            WebPushPreference::PartnerAnnouncements,
        ] as $preference) {
            $this->assertDatabaseHas('notification_preferences', [
                'user_id' => $member->id,
                'category' => $preference->value,
                'enabled' => false,
            ]);
        }

        $this->assertDatabaseMissing('notification_preferences', [
            'user_id' => $member->id,
            'category' => WebPushPreference::Administration->value,
        ]);

        expect($member->webPushSubscriptions()->whereNull('revoked_at')->count())->toBe(0);
        $this->assertDatabaseHas('partner_notification_preferences', [
            'user_id' => $member->id,
            'enabled' => false,
        ]);
    }

    public function test_removing_the_partner_role_blocks_partner_pages_and_updates_shared_roles(): void
    {
        $member = User::factory()->withProfile()->partner()->create();

        $this->actingAs($member)
            ->get(route('partner.profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.roles', fn ($roles): bool => collect($roles)
                    ->contains('name', RoleName::Partner->value)));

        $partnerRole = Role::query()->where('name', RoleName::Partner)->firstOrFail();
        $member->roles()->detach($partnerRole);

        $this->actingAs($member)
            ->get(route('partner.profile.edit'))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('member-profile.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.roles', [['name' => RoleName::User->value]]));
    }
}
