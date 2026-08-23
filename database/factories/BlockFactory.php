<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Block> */
class BlockFactory extends Factory
{
    protected $model = Block::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create();

        return [
            'blocker_user_id' => $blocker->id,
            'blocked_user_id' => $blocked->id,
        ];
    }
}
