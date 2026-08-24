<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureUserHasRole;
use App\Models\Interest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class InterestCatalogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_manage_interests(): void
    {
        $member = User::factory()->withProfile()->create();
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($member)
            ->get(route('admin.interests.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.interests.index'))
            ->assertOk();
    }

    public function test_admin_role_does_not_bypass_unrelated_profile_policy(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        $otherProfile = User::factory()->withProfile()->create()->profile;

        expect(Gate::forUser($admin)->allows('update', $otherProfile))->toBeFalse();
    }

    public function test_policies_reject_members_on_every_catalog_operation_without_the_role_middleware(): void
    {
        $member = User::factory()->withProfile()->create();
        $interest = Interest::factory()->create();
        $this->withoutMiddleware(EnsureUserHasRole::class);

        $this->actingAs($member)->get(route('admin.interests.index'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.interests.store'), [
            'name' => 'Nouveau',
        ])->assertForbidden();
        $this->actingAs($member)->patch(route('admin.interests.update', $interest), [
            'name' => 'Modifié',
        ])->assertForbidden();
        $this->actingAs($member)->delete(route('admin.interests.destroy', $interest))
            ->assertForbidden();
        $this->actingAs($member)->patch(route('admin.interests.status', $interest), [
            'is_active' => false,
        ])->assertForbidden();
        $this->actingAs($member)->patch(route('admin.interests.move', $interest), [
            'direction' => 'up',
        ])->assertForbidden();
        $this->actingAs($member)->patch(route('admin.interest-setting.update'), [
            'max_selections' => 3,
        ])->assertForbidden();
    }
}
