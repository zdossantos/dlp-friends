<?php

namespace Database\Factories;

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use App\Models\ProductOnboarding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductOnboarding> */
class ProductOnboardingFactory extends Factory
{
    protected $model = ProductOnboarding::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => ProductOnboardingStatus::InProgress,
            'step' => ProductOnboardingStep::PassDemo,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductOnboardingStatus::Completed,
            'step' => null,
        ]);
    }
}
