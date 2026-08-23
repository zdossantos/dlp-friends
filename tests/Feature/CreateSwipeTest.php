<?php

namespace Tests\Feature;

use App\Actions\CreateSwipe;
use App\Enums\ProfileVisibility;
use App\Enums\SwipeDecision;
use App\Enums\UserStatus;
use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\Profile;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateSwipeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pass_is_recorded_without_creating_a_match(): void
    {
        [$actor, $target] = $this->memberPair();
        Swipe::factory()->create([
            'actor_user_id' => $target->id,
            'target_user_id' => $actor->id,
            'decision' => SwipeDecision::Like,
        ]);

        $match = app(CreateSwipe::class)->handle($actor, $target, SwipeDecision::Pass);

        expect($match)->toBeNull();
        $this->assertDatabaseHas('swipes', [
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'decision' => SwipeDecision::Pass->value,
        ]);
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_a_first_like_is_recorded_without_creating_a_match(): void
    {
        [$actor, $target] = $this->memberPair();

        $match = app(CreateSwipe::class)->handle($actor, $target, SwipeDecision::Like);

        expect($match)->toBeNull();
        $this->assertDatabaseHas('swipes', [
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'decision' => SwipeDecision::Like->value,
        ]);
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_a_member_cannot_swipe_their_own_profile(): void
    {
        $actor = User::factory()->withProfile()->create();

        $this->expectTargetValidation(fn () => app(CreateSwipe::class)
            ->handle($actor, $actor, SwipeDecision::Like));

        $this->assertDatabaseCount('swipes', 0);
        $this->assertDatabaseCount('matches', 0);
    }

    #[DataProvider('ineligibleTargetProvider')]
    public function test_an_ineligible_target_is_rejected(string $state): void
    {
        $actor = User::factory()->withProfile()->create();
        $target = match ($state) {
            'hidden' => $this->member(profileAttributes: ['visibility' => ProfileVisibility::Hidden]),
            'incomplete' => $this->member(profileAttributes: ['onboarding_completed_at' => null]),
            'inactive' => $this->member(userAttributes: ['status' => UserStatus::PendingDeletion]),
            'minor' => $this->member(userAttributes: ['birth_date' => today()->subYears(17)]),
            'without birth date' => $this->member(userAttributes: ['birth_date' => null]),
        };

        $this->expectTargetValidation(fn () => app(CreateSwipe::class)
            ->handle($actor, $target, SwipeDecision::Like));

        $this->assertDatabaseCount('swipes', 0);
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_a_target_without_a_profile_is_rejected_without_recording_a_swipe(): void
    {
        $actor = User::factory()->withProfile()->create();
        $target = User::factory()->create();

        $this->expectTargetValidation(fn () => app(CreateSwipe::class)
            ->handle($actor, $target, SwipeDecision::Like));

        $this->assertDatabaseCount('swipes', 0);
        $this->assertDatabaseCount('matches', 0);
    }

    /** @return array<string, array{string}> */
    public static function ineligibleTargetProvider(): array
    {
        return [
            'hidden profile' => ['hidden'],
            'incomplete profile' => ['incomplete'],
            'inactive account' => ['inactive'],
            'minor account' => ['minor'],
            'missing birth date' => ['without birth date'],
        ];
    }

    #[DataProvider('blockDirectionProvider')]
    public function test_a_block_in_either_direction_rejects_the_swipe(string $direction): void
    {
        [$actor, $target] = $this->memberPair();
        [$blocker, $blocked] = $direction === 'outgoing'
            ? [$actor, $target]
            : [$target, $actor];

        Block::factory()->create([
            'blocker_user_id' => $blocker->id,
            'blocked_user_id' => $blocked->id,
        ]);

        $this->expectTargetValidation(fn () => app(CreateSwipe::class)
            ->handle($actor, $target, SwipeDecision::Like));

        $this->assertDatabaseCount('swipes', 0);
        $this->assertDatabaseCount('matches', 0);
    }

    /** @return array<string, array{string}> */
    public static function blockDirectionProvider(): array
    {
        return [
            'actor blocked target' => ['outgoing'],
            'target blocked actor' => ['incoming'],
        ];
    }

    public function test_a_target_that_becomes_hidden_after_the_pair_is_locked_is_rejected(): void
    {
        [$actor, $target] = $this->memberPair();
        $targetWasHidden = false;

        User::retrieved(function (User $retrieved) use ($target, &$targetWasHidden): void {
            if ($retrieved->isNot($target) || $targetWasHidden) {
                return;
            }

            DB::table('profiles')
                ->where('user_id', $target->id)
                ->update(['visibility' => ProfileVisibility::Hidden->value]);
            $targetWasHidden = true;
        });

        $this->expectTargetValidation(fn () => app(CreateSwipe::class)
            ->handle($actor, $target, SwipeDecision::Like));

        expect($targetWasHidden)->toBeTrue();
        $this->assertDatabaseCount('swipes', 0);
    }

    public function test_a_block_added_after_the_pair_is_locked_is_rejected(): void
    {
        [$actor, $target] = $this->memberPair();
        $blockWasAdded = false;

        User::retrieved(function (User $retrieved) use ($actor, $target, &$blockWasAdded): void {
            if ($retrieved->isNot($target) || $blockWasAdded) {
                return;
            }

            Block::query()->create([
                'blocker_user_id' => $target->id,
                'blocked_user_id' => $actor->id,
            ]);
            $blockWasAdded = true;
        });

        $this->expectTargetValidation(fn () => app(CreateSwipe::class)
            ->handle($actor, $target, SwipeDecision::Like));

        expect($blockWasAdded)->toBeTrue();
        $this->assertDatabaseCount('swipes', 0);
    }

    public function test_a_repeated_decision_is_reported_as_a_decision_validation_error(): void
    {
        [$actor, $target] = $this->memberPair();
        Swipe::factory()->create([
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'decision' => SwipeDecision::Pass,
        ]);

        try {
            app(CreateSwipe::class)->handle($actor, $target, SwipeDecision::Like);
            $this->fail('A repeated decision should have been rejected.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toBe([
                'decision' => ['Vous avez déjà évalué ce profil.'],
            ]);
        }

        $this->assertDatabaseCount('swipes', 1);
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_reciprocal_likes_create_one_canonical_match(): void
    {
        [$lowUser, $highUser] = $this->memberPair();

        $firstResult = app(CreateSwipe::class)
            ->handle($lowUser, $highUser, SwipeDecision::Like);
        $match = app(CreateSwipe::class)
            ->handle($highUser, $lowUser, SwipeDecision::Like);

        expect($firstResult)->toBeNull()
            ->and($match)->toBeInstanceOf(MemberMatch::class)
            ->and($match?->user_low_id)->toBe($lowUser->id)
            ->and($match?->user_high_id)->toBe($highUser->id);
        $this->assertDatabaseCount('swipes', 2);
        $this->assertDatabaseCount('matches', 1);
    }

    public function test_additional_attempts_leave_two_swipes_and_one_match(): void
    {
        [$lowUser, $highUser] = $this->memberPair();
        $action = app(CreateSwipe::class);

        $action->handle($lowUser, $highUser, SwipeDecision::Like);
        $action->handle($highUser, $lowUser, SwipeDecision::Like);

        foreach ([[$lowUser, $highUser], [$highUser, $lowUser]] as [$actor, $target]) {
            try {
                $action->handle($actor, $target, SwipeDecision::Like);
                $this->fail('An additional attempt should have been rejected.');
            } catch (ValidationException $exception) {
                expect($exception->errors())->toHaveKey('decision');
            }
        }

        $this->assertDatabaseCount('swipes', 2);
        $this->assertDatabaseCount('matches', 1);
    }

    public function test_preexisting_concurrent_writes_are_reported_without_creating_duplicates(): void
    {
        [$lowUser, $highUser] = $this->memberPair();
        Swipe::factory()->create([
            'actor_user_id' => $lowUser->id,
            'target_user_id' => $highUser->id,
            'decision' => SwipeDecision::Like,
        ]);
        Swipe::factory()->create([
            'actor_user_id' => $highUser->id,
            'target_user_id' => $lowUser->id,
            'decision' => SwipeDecision::Like,
        ]);
        MemberMatch::factory()->create([
            'user_low_id' => $lowUser->id,
            'user_high_id' => $highUser->id,
        ]);

        foreach ([[$lowUser, $highUser], [$highUser, $lowUser]] as [$actor, $target]) {
            try {
                app(CreateSwipe::class)->handle($actor, $target, SwipeDecision::Like);
                $this->fail('A concurrent duplicate should have been rejected.');
            } catch (ValidationException $exception) {
                expect($exception->errors())->toBe([
                    'decision' => ['Vous avez déjà évalué ce profil.'],
                ]);
            }
        }

        $this->assertDatabaseCount('swipes', 2);
        $this->assertDatabaseCount('matches', 1);
    }

    public function test_an_unrelated_database_error_is_not_converted_to_a_duplicate_decision(): void
    {
        [$actor, $target] = $this->memberPair();
        [$blocker, $blocked] = $this->memberPair();
        Block::factory()->create([
            'blocker_user_id' => $blocker->id,
            'blocked_user_id' => $blocked->id,
        ]);
        Swipe::creating(static function () use ($blocker, $blocked): void {
            Block::query()->create([
                'blocker_user_id' => $blocker->id,
                'blocked_user_id' => $blocked->id,
            ]);
        });

        try {
            app(CreateSwipe::class)->handle($actor, $target, SwipeDecision::Like);
            $this->fail('An unrelated database error should be rethrown.');
        } catch (QueryException $exception) {
            expect($exception)->toBeInstanceOf(QueryException::class);
        } finally {
            Swipe::flushEventListeners();
        }
    }

    /** @return array{User, User} */
    private function memberPair(): array
    {
        return [$this->member(), $this->member()];
    }

    /**
     * @param  array<string, mixed>  $profileAttributes
     * @param  array<string, mixed>  $userAttributes
     */
    private function member(array $profileAttributes = [], array $userAttributes = []): User
    {
        $user = User::factory()->create($userAttributes);

        Profile::factory()->complete()->for($user)->create($profileAttributes);

        return $user;
    }

    private function expectTargetValidation(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The target should have been rejected.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('target');
        }
    }
}
