<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\GroupChat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GroupChat> */
class GroupChatFactory extends Factory
{
    protected $model = GroupChat::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
        ];
    }
}
