<?php

namespace Database\Factories;

use App\Models\PassionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PassionCategory> */
class PassionCategoryFactory extends Factory
{
    protected $model = PassionCategory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
