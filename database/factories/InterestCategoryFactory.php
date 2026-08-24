<?php

namespace Database\Factories;

use App\Models\InterestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InterestCategory> */
class InterestCategoryFactory extends Factory
{
    protected $model = InterestCategory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
