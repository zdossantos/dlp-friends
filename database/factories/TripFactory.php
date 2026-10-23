<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Trip> */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['organizer_user_id' => User::factory()->withProfile(), 'transport' => 'car', 'direction' => 'outbound', 'general_departure' => 'Paris', 'destination' => 'Disneyland Paris', 'departs_at' => now()->addDays(2), 'meeting_point' => 'Entrée de la gare', 'audience' => 'matches', 'car_seats' => 2];
    }
}
