<?php

namespace App\Actions;

use App\Enums\ProfileVisibility;
use App\Enums\SwipeDecision;
use App\Enums\UserStatus;
use App\Models\Block;
use App\Models\MemberMatch;
use App\Models\Profile;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSwipe
{
    public function handle(User $actor, User $target, SwipeDecision $decision): ?MemberMatch
    {
        $target->load('profile');
        $this->ensureTargetIsEligible($actor, $target);
        $this->ensurePairIsNotBlocked($actor, $target);

        try {
            return DB::transaction(function () use ($actor, $target, $decision): ?MemberMatch {
                [$lowId, $highId] = collect([$actor->id, $target->id])
                    ->sort()
                    ->values()
                    ->all();

                $lockedUsers = User::query()
                    ->whereKey([$lowId, $highId])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lockedActor = $lockedUsers->get($actor->id);
                $lockedTarget = $lockedUsers->get($target->id);

                if (! $lockedActor instanceof User || ! $lockedTarget instanceof User) {
                    $this->throwUnavailableTarget();
                }

                $lockedTarget->setRelation(
                    'profile',
                    Profile::query()
                        ->where('user_id', $lockedTarget->id)
                        ->lockForUpdate()
                        ->first(),
                );
                $this->ensureTargetIsEligible($lockedActor, $lockedTarget);
                $this->ensurePairIsNotBlocked($lockedActor, $lockedTarget);

                Swipe::query()->create([
                    'actor_user_id' => $actor->id,
                    'target_user_id' => $target->id,
                    'decision' => $decision,
                ]);

                if ($decision === SwipeDecision::Pass || ! Swipe::query()
                    ->where('actor_user_id', $target->id)
                    ->where('target_user_id', $actor->id)
                    ->where('decision', SwipeDecision::Like)
                    ->exists()) {
                    return null;
                }

                MemberMatch::query()->insertOrIgnore([
                    'user_low_id' => $lowId,
                    'user_high_id' => $highId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $match = MemberMatch::query()
                    ->where('user_low_id', $lowId)
                    ->where('user_high_id', $highId)
                    ->firstOrFail();

                DB::table('conversations')->insertOrIgnore([
                    'match_id' => $match->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $match;
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicateSwipeViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'decision' => __('Vous avez déjà évalué ce profil.'),
            ]);
        }
    }

    private function ensureTargetIsEligible(User $actor, User $target): void
    {
        $profile = $target->profile;
        $adultCutoff = today()->subYears(18);

        if (
            $actor->is($target)
            || $target->status !== UserStatus::Active
            || $target->birth_date === null
            || $target->birth_date->isAfter($adultCutoff)
            || $profile === null
            || ! $profile->isComplete()
            || $profile->visibility !== ProfileVisibility::Visible
        ) {
            $this->throwUnavailableTarget();
        }
    }

    private function ensurePairIsNotBlocked(User $actor, User $target): void
    {
        $isBlocked = Block::query()
            ->where(function (Builder $query) use ($actor, $target): void {
                $query
                    ->where('blocker_user_id', $actor->id)
                    ->where('blocked_user_id', $target->id);
            })
            ->orWhere(function (Builder $query) use ($actor, $target): void {
                $query
                    ->where('blocker_user_id', $target->id)
                    ->where('blocked_user_id', $actor->id);
            })
            ->exists();

        if ($isBlocked) {
            $this->throwUnavailableTarget();
        }
    }

    private function throwUnavailableTarget(): never
    {
        throw ValidationException::withMessages([
            'target' => 'Ce profil n’est pas disponible.',
        ]);
    }

    private function isDuplicateSwipeViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $sqlState = (string) ($errorInfo[0] ?? '');
        $driverCode = (int) ($errorInfo[1] ?? 0);
        $driverMessage = (string) ($errorInfo[2] ?? $exception->getMessage());

        if ($sqlState !== '23000') {
            return false;
        }

        if ($driverCode === 1062) {
            return str_contains($driverMessage, 'swipes_actor_user_id_target_user_id_unique');
        }

        return in_array($driverCode, [19, 2067], true)
            && str_contains(
                $driverMessage,
                'UNIQUE constraint failed: swipes.actor_user_id, swipes.target_user_id',
            );
    }
}
