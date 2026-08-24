<?php

namespace Tests\Unit;

use App\Actions\SyncProfileInterests;
use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SyncProfileInterestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_effective_interests_without_deleting_suspended_history(): void
    {
        $profile = User::factory()->withProfile()->create()->profile;
        [$keep, $remove, $suspended] = Interest::factory()->count(3)->create();
        $profile->interestHistory()->attach([
            $keep->id => ['is_selected' => true],
            $remove->id => ['is_selected' => true],
            $suspended->id => ['is_selected' => false],
        ]);

        app(SyncProfileInterests::class)->handle($profile, [$keep->id]);

        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $profile->id,
            'interest_id' => $suspended->id,
            'is_selected' => false,
        ]);
        $this->assertDatabaseMissing('interest_profile', [
            'profile_id' => $profile->id,
            'interest_id' => $remove->id,
        ]);
        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $profile->id,
            'interest_id' => $keep->id,
            'is_selected' => true,
        ]);
    }

    public function test_it_rejects_an_interest_that_is_inactive_when_the_write_transaction_runs(): void
    {
        $profile = User::factory()->withProfile()->create()->profile;
        $inactive = Interest::factory()->create(['is_active' => false]);

        $this->expectInterestIdsValidation(
            fn () => app(SyncProfileInterests::class)->handle($profile, [$inactive->id]),
        );

        $this->assertDatabaseMissing('interest_profile', [
            'profile_id' => $profile->id,
            'interest_id' => $inactive->id,
        ]);
    }

    public function test_it_rejects_a_missing_interest_when_the_write_transaction_runs(): void
    {
        $profile = User::factory()->withProfile()->create()->profile;

        $this->expectInterestIdsValidation(
            fn () => app(SyncProfileInterests::class)->handle($profile, [999_999]),
        );

        $this->assertDatabaseMissing('interest_profile', [
            'profile_id' => $profile->id,
        ]);
    }

    public function test_it_rejects_new_selections_above_the_locked_limit(): void
    {
        InterestSetting::current()->update(['max_selections' => 1]);
        $profile = User::factory()->withProfile()->create()->profile;
        $interests = Interest::factory()->count(2)->create();

        $this->expectInterestIdsValidation(
            fn () => app(SyncProfileInterests::class)
                ->handle($profile, $interests->pluck('id')->all()),
        );

        $this->assertDatabaseMissing('interest_profile', [
            'profile_id' => $profile->id,
        ]);
    }

    public function test_it_preserves_a_grandfathered_over_limit_set(): void
    {
        $profile = User::factory()->withProfile()->create()->profile;
        $interests = Interest::factory()->count(2)->create();
        $profile->interests()->attach($interests);
        InterestSetting::current()->update(['max_selections' => 1]);

        app(SyncProfileInterests::class)
            ->handle($profile, $interests->pluck('id')->all());

        expect($profile->interests()->pluck('interests.id')->all())
            ->toEqualCanonicalizing($interests->pluck('id')->all());
    }

    public function test_it_rejects_an_addition_while_a_grandfathered_set_remains_over_limit(): void
    {
        $profile = User::factory()->withProfile()->create()->profile;
        [$first, $second, $replacement] = Interest::factory()->count(3)->create();
        $profile->interests()->attach([$first->id, $second->id]);
        InterestSetting::current()->update(['max_selections' => 1]);

        $this->expectInterestIdsValidation(
            fn () => app(SyncProfileInterests::class)
                ->handle($profile, [$first->id, $replacement->id]),
        );

        expect($profile->interests()->pluck('interests.id')->all())
            ->toEqualCanonicalizing([$first->id, $second->id]);
    }

    private function expectInterestIdsValidation(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The submitted interests should have been rejected.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('interest_ids');
        }
    }
}
