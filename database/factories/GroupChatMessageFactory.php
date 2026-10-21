<?php

namespace Database\Factories;

use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GroupChatMessage> */
class GroupChatMessageFactory extends Factory
{
    protected $model = GroupChatMessage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'group_chat_id' => GroupChat::factory(),
            'author_user_id' => User::factory(),
            'content' => fake()->text(200),
        ];
    }
}
