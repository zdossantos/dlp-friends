<?php

namespace Database\Factories;

use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Message> */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'conversation_id' => fn () => MemberMatch::factory()->create()->conversation()->create()->id,
            'author_user_id' => User::factory(),
            'content' => fake()->text(200),
        ];
    }
}
