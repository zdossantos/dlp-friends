<?php

namespace Database\Factories;

use App\Enums\EventRegistrationMode;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organizer_user_id' => User::factory()->withProfile(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'general_location' => 'Disneyland Park',
            'detailed_location' => 'Devant la gare de Main Street, U.S.A.',
            'starts_at' => now()->addWeek(),
            'capacity' => 6,
            'registration_mode' => EventRegistrationMode::Automatic,
            'cancelled_at' => null,
        ];
    }
}
