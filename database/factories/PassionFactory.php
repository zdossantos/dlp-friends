<?php

namespace Database\Factories;

use App\Models\Passion;
use App\Models\PassionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Passion> */
class PassionFactory extends Factory
{
    protected $model = Passion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'passion_category_id' => PassionCategory::factory(),
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
