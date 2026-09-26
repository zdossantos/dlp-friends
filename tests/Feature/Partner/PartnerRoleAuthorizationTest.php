<?php

namespace Tests\Feature\Partner;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_partner_only_account_cannot_access_member_or_admin_features(): void
    {
        $partner = User::factory()->partnerOnly()->create();

        $this->actingAs($partner)->get(route('member-profile.create'))->assertForbidden();
        $this->actingAs($partner)->get(route('discovery.index'))->assertForbidden();
        $this->actingAs($partner)->get(route('events.index'))->assertForbidden();
        $this->actingAs($partner)->get(route('conversations.index'))->assertForbidden();
        $this->actingAs($partner)->get(route('notifications.index'))->assertForbidden();
        $this->actingAs($partner)->get(route('account.edit'))->assertForbidden();
        $this->actingAs($partner)->get(route('admin.members.index'))->assertForbidden();
    }

    public function test_an_admin_without_the_user_role_keeps_administration_access(): void
    {
        $admin = User::factory()->admin()->create();
        $userRole = Role::query()->where('name', RoleName::User)->firstOrFail();
        $admin->roles()->detach($userRole);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.members.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.avatars.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.interests.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.onboarding.index'))->assertOk();
        $this->actingAs($admin)->get(route('account.edit'))->assertForbidden();
    }
}
