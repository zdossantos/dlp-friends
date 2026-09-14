<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManageMemberRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inertia.testing.ensure_pages_exist', false);
    }

    public function test_an_admin_exactly_synchronizes_manageable_roles_and_audits_each_change(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.members.roles.update', $member), [
                'roles' => [RoleName::Partner->value],
                'confirmed' => true,
            ])
            ->assertRedirect(route('admin.members.index'));

        $roles = $member->fresh('roles')->roles->pluck('name');

        expect($roles)->toContain(RoleName::Partner)
            ->not->toContain(RoleName::User);
        $this->assertDatabaseHas('role_audits', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $member->id,
            'role' => RoleName::User->value,
            'action' => 'removed',
        ]);
        $this->assertDatabaseHas('role_audits', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $member->id,
            'role' => RoleName::Partner->value,
            'action' => 'assigned',
        ]);
        $this->assertDatabaseCount('role_audits', 2);
    }

    public function test_an_unchanged_role_submission_has_no_effect_and_creates_no_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->partner()->create();

        $this->actingAs($admin)
            ->patch(route('admin.members.roles.update', $member), [
                'roles' => [RoleName::User->value, RoleName::Partner->value],
                'confirmed' => true,
            ])
            ->assertRedirect(route('admin.members.index'));

        expect($member->fresh('roles')->roles->pluck('name'))
            ->toContain(RoleName::User, RoleName::Partner);
        $this->assertDatabaseCount('role_audits', 0);
    }

    public function test_removing_the_partner_role_retains_partner_data(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->partner()->create();
        $partnerProfile = PartnerProfile::factory()->for($member)->create();

        $this->actingAs($admin)
            ->patch(route('admin.members.roles.update', $member), [
                'roles' => [RoleName::User->value],
                'confirmed' => true,
            ])
            ->assertRedirect(route('admin.members.index'));

        expect($member->fresh('roles')->hasRole(RoleName::Partner))->toBeFalse();
        $this->assertDatabaseHas('partner_profiles', [
            'id' => $partnerProfile->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_an_admin_cannot_change_their_own_roles(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.members.roles.update', $admin), [
                'roles' => [RoleName::Partner->value],
                'confirmed' => true,
            ])
            ->assertForbidden();

        expect($admin->fresh('roles')->roles->pluck('name'))
            ->toContain(RoleName::User, RoleName::Admin)
            ->not->toContain(RoleName::Partner);
        $this->assertDatabaseCount('role_audits', 0);
    }

    public function test_another_admins_manageable_roles_can_change_without_changing_the_admin_role(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.members.roles.update', $otherAdmin), [
                'roles' => [],
                'confirmed' => true,
            ])
            ->assertRedirect(route('admin.members.index'));

        expect($otherAdmin->fresh('roles')->roles->pluck('name'))
            ->toContain(RoleName::Admin)
            ->not->toContain(RoleName::User);
        $this->actingAs($otherAdmin)
            ->get(route('admin.members.index'))
            ->assertOk();
        $this->assertDatabaseHas('role_audits', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $otherAdmin->id,
            'role' => RoleName::User->value,
            'action' => 'removed',
        ]);
    }

    public function test_the_admin_role_cannot_be_submitted_from_member_role_management(): void
    {
        $admin = User::factory()->admin()->create();

        $member = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.members.roles.update', $member), [
                'roles' => [RoleName::User->value, RoleName::Admin->value],
                'confirmed' => true,
            ])
            ->assertSessionHasErrors('roles');

        expect($member->fresh('roles')->hasRole(RoleName::Admin))->toBeFalse();
        $this->assertDatabaseCount('role_audits', 0);
    }

    public function test_role_changes_require_explicit_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.members.roles.update', $member), [
                'roles' => [RoleName::Partner->value],
                'confirmed' => false,
            ])
            ->assertSessionHasErrors('confirmed');

        expect($member->fresh('roles')->roles->pluck('name'))
            ->toContain(RoleName::User)
            ->not->toContain(RoleName::Partner);
        $this->assertDatabaseCount('role_audits', 0);
    }

    public function test_a_non_admin_cannot_manage_roles(): void
    {
        $actor = User::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($actor)
            ->patch(route('admin.members.roles.update', $member), [
                'roles' => [RoleName::Partner->value],
                'confirmed' => true,
            ])
            ->assertForbidden();

        expect($member->fresh('roles')->hasRole(RoleName::Partner))->toBeFalse();
        $this->assertDatabaseCount('role_audits', 0);
    }

    public function test_the_member_catalog_exposes_only_role_names_and_role_management_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->partner()->create(['email' => 'partner@example.test']);

        $this->actingAs($admin)
            ->get(route('admin.members.index', ['search' => $member->email]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.roles', fn (Collection $roles): bool => $roles
                    ->map(fn (array $role): array => array_keys($role))
                    ->every(fn (array $keys): bool => $keys === ['name'])
                    && $roles->pluck('name')->sort()->values()->all() === ['partner', 'user'])
                ->where('members.data.0.can_manage_roles', true));
    }
}
