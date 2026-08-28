<?php

namespace App\Actions;

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use App\Models\ProductOnboarding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdvanceProductOnboarding
{
    public function start(User $user): ProductOnboarding
    {
        return DB::transaction(function () use ($user): ProductOnboarding {
            $this->lockUser($user);

            $onboarding = ProductOnboarding::query()
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if ($onboarding !== null) {
                return $onboarding;
            }

            $onboarding ??= new ProductOnboarding(['user_id' => $user->getKey()]);
            $onboarding->forceFill([
                'status' => ProductOnboardingStatus::InProgress,
                'step' => ProductOnboardingStep::PassDemo,
            ])->save();

            return $onboarding->refresh();
        });
    }

    public function advance(User $user, ProductOnboardingStep $expectedStep): ProductOnboarding
    {
        return DB::transaction(function () use ($user, $expectedStep): ProductOnboarding {
            $onboarding = $this->lockProgress($user);

            if ($onboarding->status !== ProductOnboardingStatus::InProgress || $onboarding->step !== $expectedStep) {
                $this->invalidTransition();
            }

            $nextStep = match ($expectedStep) {
                ProductOnboardingStep::PassDemo => ProductOnboardingStep::LikeDemo,
                ProductOnboardingStep::LikeDemo => ProductOnboardingStep::MatchDemo,
                ProductOnboardingStep::MatchDemo => ProductOnboardingStep::ConversationDemo,
                ProductOnboardingStep::ConversationDemo => null,
            };

            if ($nextStep === null) {
                $this->invalidTransition();
            }

            $onboarding->update(['step' => $nextStep]);

            return $onboarding->refresh();
        });
    }

    public function complete(User $user): ProductOnboarding
    {
        return DB::transaction(function () use ($user): ProductOnboarding {
            $onboarding = $this->lockProgress($user);

            if ($onboarding->status !== ProductOnboardingStatus::InProgress
                || $onboarding->step !== ProductOnboardingStep::ConversationDemo) {
                $this->invalidTransition();
            }

            $onboarding->update([
                'status' => ProductOnboardingStatus::Completed,
                'step' => null,
            ]);

            return $onboarding->refresh();
        });
    }

    private function lockProgress(User $user): ProductOnboarding
    {
        $this->lockUser($user);

        $onboarding = ProductOnboarding::query()
            ->whereBelongsTo($user)
            ->lockForUpdate()
            ->first();

        if ($onboarding === null) {
            $this->invalidTransition();
        }

        return $onboarding;
    }

    private function lockUser(User $user): void
    {
        User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
    }

    private function invalidTransition(): never
    {
        throw ValidationException::withMessages([
            'onboarding' => __('Cette étape du tutoriel n’est pas encore disponible.'),
        ]);
    }
}
