<?php

namespace Database\Factories;

use App\Enums\SwipeDecision;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Swipe> */
class SwipeFactory extends Factory
{
    protected $model = Swipe::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        return [
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'decision' => fake()->randomElement(SwipeDecision::cases()),
        ];
    }
}
