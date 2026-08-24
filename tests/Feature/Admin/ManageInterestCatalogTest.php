<?php

namespace Tests\Feature\Admin;

use App\Models\Interest;
use App\Models\InterestCategory;
use App\Models\InterestSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManageInterestCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_ordered_interests_with_historical_usage_and_the_setting(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        InterestSetting::current()->update(['max_selections' => 7]);
        $later = Interest::factory()->create([
            'name' => 'Spectacles',
            'is_active' => false,
            'sort_order' => 20,
        ]);
        $earlier = Interest::factory()->create([
            'name' => 'Attractions',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $profile = User::factory()->withProfile()->create()->profile;
        $profile->interestHistory()->attach($later, ['is_selected' => false]);
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.interests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Interests/Index')
                ->where('interests', [
                    [
                        'id' => $earlier->id,
                        'name' => 'Attractions',
                        'is_active' => true,
                        'sort_order' => 10,
                        'profiles_count' => 0,
                    ],
                    [
                        'id' => $later->id,
                        'name' => 'Spectacles',
                        'is_active' => false,
                        'sort_order' => 20,
                        'profiles_count' => 1,
                    ],
                ])
                ->where('setting', ['max_selections' => 7]));
    }

    public function test_admin_can_create_and_rename_an_interest_with_normalized_unique_names(): void
    {
        $category = InterestCategory::factory()->create(['name' => 'Général']);
        Interest::factory()->for($category, 'category')->create([
            'name' => 'Existant',
            'sort_order' => 4,
        ]);
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)->post(route('admin.interests.store'), [
            'name' => '  Nouvel   intérêt  ',
        ])->assertRedirect();

        $created = Interest::query()->where('name', 'Nouvel intérêt')->firstOrFail();
        expect($created->interest_category_id)->toBe($category->id)
            ->and($created->is_active)->toBeTrue()
            ->and($created->sort_order)->toBe(5);

        $this->actingAs($admin)->patch(route('admin.interests.update', $created), [
            'name' => '  Intérêt   renommé ',
        ])->assertRedirect();
        $this->assertDatabaseHas('interests', [
            'id' => $created->id,
            'name' => 'Intérêt renommé',
        ]);

        $this->actingAs($admin)->patch(route('admin.interests.update', $created), [
            'name' => ' Existant ',
        ])->assertSessionHasErrors('name');
        $this->assertDatabaseHas('interests', [
            'id' => $created->id,
            'name' => 'Intérêt renommé',
        ]);
    }

    public function test_first_created_interest_starts_the_zero_based_order(): void
    {
        InterestCategory::factory()->create(['name' => 'Général']);
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)->post(route('admin.interests.store'), [
            'name' => 'Premier intérêt',
        ])->assertRedirect();

        $this->assertDatabaseHas('interests', [
            'name' => 'Premier intérêt',
            'sort_order' => 0,
        ]);
    }

    public function test_admin_can_archive_and_reactivate_an_interest_through_the_status_endpoint(): void
    {
        $interest = Interest::factory()->create(['is_active' => true]);
        $profile = User::factory()->withProfile()->create()->profile;
        $profile->interestHistory()->attach($interest, ['is_selected' => true]);
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.interests.status', $interest), [
            'is_active' => false,
        ])->assertRedirect();

        expect($interest->fresh()->is_active)->toBeFalse();
        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $profile->id,
            'interest_id' => $interest->id,
            'is_selected' => false,
        ]);

        $this->actingAs($admin)->patch(route('admin.interests.status', $interest), [
            'is_active' => true,
        ])->assertRedirect();
        expect($interest->fresh()->is_active)->toBeTrue();

        $this->actingAs($admin)->patch(route('admin.interests.status', $interest), [
            'is_active' => 'not-a-boolean',
        ])->assertSessionHasErrors('is_active');
    }

    public function test_moving_an_interest_uses_its_ordered_neighbor_and_normalizes_all_positions(): void
    {
        $last = Interest::factory()->create(['name' => 'Dernier', 'sort_order' => 30]);
        $first = Interest::factory()->create(['name' => 'Premier', 'sort_order' => 10]);
        $middle = Interest::factory()->create(['name' => 'Milieu', 'sort_order' => 20]);
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.interests.move', $middle), [
            'direction' => 'up',
        ])->assertRedirect();

        expect(Interest::query()->orderBy('sort_order')->pluck('id')->all())
            ->toBe([$middle->id, $first->id, $last->id]);
        expect(Interest::query()->orderBy('sort_order')->pluck('sort_order')->all())
            ->toBe([0, 1, 2]);

        $this->actingAs($admin)->patch(route('admin.interests.move', $middle), [
            'direction' => 'sideways',
        ])->assertSessionHasErrors('direction');
    }

    public function test_boundary_move_still_normalizes_gapped_positions(): void
    {
        $first = Interest::factory()->create(['sort_order' => 10]);
        $second = Interest::factory()->create(['sort_order' => 30]);
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.interests.move', $first), [
            'direction' => 'up',
        ])->assertRedirect();

        expect(Interest::query()->orderBy('sort_order')->pluck('id')->all())
            ->toBe([$first->id, $second->id]);
        expect(Interest::query()->orderBy('sort_order')->pluck('sort_order')->all())
            ->toBe([0, 1]);
    }

    public function test_used_interest_cannot_be_deleted_even_when_its_history_is_suspended(): void
    {
        $used = Interest::factory()->create();
        $profile = User::factory()->withProfile()->create()->profile;
        $profile->interestHistory()->attach($used, ['is_selected' => false]);
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.interests.destroy', $used))
            ->assertSessionHasErrors('interest');

        $this->assertDatabaseHas('interests', ['id' => $used->id]);
    }

    public function test_deleting_an_unused_interest_normalizes_remaining_positions(): void
    {
        $first = Interest::factory()->create(['sort_order' => 10]);
        $deleted = Interest::factory()->create(['sort_order' => 20]);
        $last = Interest::factory()->create(['sort_order' => 30]);
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.interests.destroy', $deleted))
            ->assertRedirect();

        $this->assertDatabaseMissing('interests', ['id' => $deleted->id]);
        expect(Interest::query()->orderBy('sort_order')->pluck('id')->all())
            ->toBe([$first->id, $last->id]);
        expect(Interest::query()->orderBy('sort_order')->pluck('sort_order')->all())
            ->toBe([0, 1]);
    }

    public function test_admin_can_lower_the_limit_without_deleting_existing_selections(): void
    {
        $interests = Interest::factory()->count(2)->create();
        $profile = User::factory()->withProfile()->create()->profile;
        $profile->interests()->attach($interests);
        $admin = User::factory()->withProfile()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.interest-setting.update'), [
            'max_selections' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('interest_settings', [
            'id' => 1,
            'max_selections' => 1,
        ]);
        expect($profile->interests()->pluck('interests.id')->all())
            ->toEqualCanonicalizing($interests->pluck('id')->all());

        foreach ([0, 101, 'three'] as $invalidLimit) {
            $this->actingAs($admin)->patch(route('admin.interest-setting.update'), [
                'max_selections' => $invalidLimit,
            ])->assertSessionHasErrors('max_selections');
        }

        expect(InterestSetting::current()->max_selections)->toBe(1);
    }
}
