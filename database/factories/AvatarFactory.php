<?php

namespace Database\Factories;

use App\Models\Avatar;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Avatar> */
class AvatarFactory extends Factory
{
    protected $model = Avatar::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'image_path' => 'avatars/'.fake()->uuid().'.png',
            'primary_color' => fake()->hexColor(),
            'secondary_color' => fake()->hexColor(),
            'is_active' => true,
            'sort_order' => fake()->unique()->numberBetween(0, 100000),
        ];
    }
}
