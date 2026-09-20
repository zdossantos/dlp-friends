<?php

namespace Tests\Feature\Settings;

use App\Actions\UpdatePartnerNotificationPreference;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerNotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_announcements_are_opt_in_and_default_to_disabled(): void
    {
        $member = User::factory()->withProfile()->create();

        $this->actingAs($member)
            ->get(route('notification-preferences.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Notifications')
                ->where('partnerAnnouncementsEnabled', false)
                ->where('auth.user.roles', [['name' => RoleName::User->value]]));

        $this->assertDatabaseMissing('partner_notification_preferences', [
            'user_id' => $member->id,
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

    public function test_partner_notification_settings_require_the_user_role(): void
    {
        $partner = User::factory()->partnerOnly()->create();

        $this->actingAs($partner)
            ->get(route('notification-preferences.edit'))
            ->assertForbidden();

        $this->actingAs($partner)
            ->patch(route('notification-preferences.update'), [
                'partner_announcements' => true,
            ])
            ->assertForbidden();
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
