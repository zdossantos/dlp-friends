<?php

namespace Database\Factories;

use App\Models\Interest;
use App\Models\InterestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Interest> */
class InterestFactory extends Factory
{
    protected $model = Interest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'interest_category_id' => InterestCategory::factory(),
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
