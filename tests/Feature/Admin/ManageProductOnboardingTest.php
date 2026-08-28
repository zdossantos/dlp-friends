<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use App\Enums\UserStatus;
use App\Models\Avatar;
use App\Models\ProductOnboarding;
use App\Models\ProductOnboardingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManageProductOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_configures_two_distinct_active_demo_avatars(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        [$passAvatar, $likeAvatar] = Avatar::factory()->count(2)->create();
        $inactive = Avatar::factory()->create(['is_active' => false]);

        $this->actingAs($admin)->patch(route('admin.onboarding.update'), [
            'pass_avatar_id' => $passAvatar->id,
            'like_avatar_id' => $likeAvatar->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('product_onboarding_settings', [
            'id' => ProductOnboardingSetting::SINGLETON_ID,
            'pass_avatar_id' => $passAvatar->id,
            'like_avatar_id' => $likeAvatar->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.onboarding.update'), [
            'pass_avatar_id' => $passAvatar->id,
            'like_avatar_id' => $passAvatar->id,
        ])->assertSessionHasErrors('like_avatar_id');

        $this->actingAs($admin)->patch(route('admin.onboarding.update'), [
            'pass_avatar_id' => $inactive->id,
            'like_avatar_id' => $likeAvatar->id,
        ])->assertSessionHasErrors('pass_avatar_id');
    }

    public function test_non_admin_cannot_manage_product_onboarding(): void
    {
        $member = User::factory()->withProfile()->create();

        $this->actingAs($member)->get(route('admin.onboarding.index'))->assertForbidden();
        $this->actingAs($member)->patch(route('admin.onboarding.update'))->assertForbidden();
    }

    public function test_tutorial_avatars_cannot_be_archived_or_deleted(): void
    {
        $admin = User::factory()->withProfile()->admin()->create();
        [$passAvatar, $likeAvatar] = Avatar::factory()->count(2)->create();
        ProductOnboardingSetting::query()->create([
            'id' => ProductOnboardingSetting::SINGLETON_ID,
            'pass_avatar_id' => $passAvatar->id,
            'like_avatar_id' => $likeAvatar->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.avatars.status', $passAvatar), ['is_active' => false])
            ->assertSessionHasErrors('avatar');
        $this->actingAs($admin)
            ->delete(route('admin.avatars.destroy', $likeAvatar))
            ->assertSessionHasErrors('avatar');

        expect($passAvatar->fresh()?->is_active)->toBeTrue()
            ->and($likeAvatar->fresh())->not->toBeNull();
    }

    public function test_admin_sees_eligible_member_stats_and_progress_table(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $admin = User::factory()->withProfile()->admin()->create(['created_at' => now()->subDays(10)]);
        ProductOnboarding::factory()->for($admin)->create([
            'status' => ProductOnboardingStatus::Completed,
            'step' => null,
            'updated_at' => now()->subDays(4),
        ]);
        $notStarted = User::factory()->withProfile()->create(['created_at' => now()]);
        $inProgress = $this->eligibleMember(ProductOnboardingStatus::InProgress, ProductOnboardingStep::LikeDemo, now()->subDay());
        $completed = $this->eligibleMember(ProductOnboardingStatus::Completed, null, now()->subDays(2));
        $skipped = $this->eligibleMember(ProductOnboardingStatus::Skipped, null, now()->subDays(3));

        User::factory()->withProfile()->unverified()->create();
        User::factory()->withProfile()->create(['birth_date' => today()->subYears(17)]);
        User::factory()->withProfile()->create(['status' => UserStatus::PendingDeletion]);
        User::factory()->create();

        $this->actingAs($admin)->get(route('admin.onboarding.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Onboarding/Index')
                ->where('stats', [
                    'not_started' => 1,
                    'in_progress' => 1,
                    'completed' => 2,
                    'skipped' => 1,
                    'completion_rate' => 40,
                ])
                ->where('members.total', 5)
                ->where('members.per_page', 20)
                ->where('members.data.0.id', $notStarted->id)
                ->where('members.data.1.id', $inProgress->id)
                ->where('members.data.2.id', $completed->id)
                ->where('members.data.3.id', $skipped->id)
                ->where('members.data.4.id', $admin->id)
                ->where('members.data.0.status', ProductOnboardingStatus::NotStarted->value)
                ->where('members.data.1.step', ProductOnboardingStep::LikeDemo->value));
    }

    private function eligibleMember(
        ProductOnboardingStatus $status,
        ?ProductOnboardingStep $step,
        \DateTimeInterface $updatedAt,
    ): User {
        $user = User::factory()->withProfile()->create();
        ProductOnboarding::factory()->for($user)->create([
            'status' => $status,
            'step' => $step,
            'updated_at' => $updatedAt,
        ]);

        return $user;
    }
}
