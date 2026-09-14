<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventChat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventChat> */
class EventChatFactory extends Factory
{
    protected $model = EventChat::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
        ];
    }
}
