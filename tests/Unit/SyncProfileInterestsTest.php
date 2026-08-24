<?php

namespace Tests\Unit;

use App\Actions\SyncProfileInterests;
use App\Models\Interest;
use App\Models\InterestSetting;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_it_preserves_an_omitted_inactive_legacy_association_marked_selected(): void
    {
        $profile = User::factory()->withProfile()->create()->profile;
        $legacyInactive = Interest::factory()->create(['is_active' => false]);
        $profile->interestHistory()->attach($legacyInactive, ['is_selected' => true]);

        app(SyncProfileInterests::class)->handle($profile, []);

        $this->assertDatabaseHas('interest_profile', [
            'profile_id' => $profile->id,
            'interest_id' => $legacyInactive->id,
            'is_selected' => true,
        ]);
    }

    public function test_it_locks_interests_then_setting_then_profile_before_pivot_writes(): void
    {
        $profile = User::factory()->withProfile()->create()->profile;
        $interest = Interest::factory()->create();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        app(SyncProfileInterests::class)->handle($profile, [$interest->id]);

        $interestLockIndex = collect($queries)->search(
            fn (string $sql): bool => preg_match(
                '/select [`"]id[`"], [`"]is_active[`"] from [`"]interests[`"] /i',
                $sql,
            ) === 1,
        );
        $settingLockIndex = collect($queries)->search(
            fn (string $sql): bool => preg_match(
                '/select \* from [`"]interest_settings[`"] where [`"]interest_settings[`"]\.[`"]id[`"] = \?/i',
                $sql,
            ) === 1,
        );
        $profileLockIndex = collect($queries)->search(
            fn (string $sql): bool => preg_match(
                '/select \* from [`"]profiles[`"] where [`"]profiles[`"]\.[`"]id[`"] = \?/i',
                $sql,
            ) === 1,
        );
        $pivotWriteIndex = collect($queries)->search(
            fn (string $sql): bool => preg_match('/delete from [`"]interest_profile[`"] /i', $sql) === 1,
        );

        expect($interestLockIndex)->not->toBeFalse()
            ->and($settingLockIndex)->not->toBeFalse()
            ->and($profileLockIndex)->not->toBeFalse()
            ->and($pivotWriteIndex)->not->toBeFalse()
            ->and($interestLockIndex)->toBeLessThan($settingLockIndex)
            ->and($settingLockIndex)->toBeLessThan($profileLockIndex)
            ->and($profileLockIndex)->toBeLessThan($pivotWriteIndex);
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
