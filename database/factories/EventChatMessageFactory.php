<?php

namespace Database\Factories;

use App\Models\EventChat;
use App\Models\EventChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventChatMessage> */
class EventChatMessageFactory extends Factory
{
    protected $model = EventChatMessage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'event_chat_id' => EventChat::factory(),
            'author_user_id' => User::factory(),
            'content' => fake()->text(200),
        ];
    }
}
