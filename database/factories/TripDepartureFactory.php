<?php

namespace Database\Factories;

use App\Models\TripDeparture;
use App\Models\TripRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TripDeparture> */
class TripDepartureFactory extends Factory
{
    protected $model = TripDeparture::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['trip_registration_id' => TripRegistration::factory()->state(['status' => 'withdrawn']), 'trip_id' => fn (array $attributes) => TripRegistration::query()->whereKey($attributes['trip_registration_id'])->sole()->trip_id, 'user_id' => fn (array $attributes) => TripRegistration::query()->whereKey($attributes['trip_registration_id'])->sole()->user_id, 'scheduled_departure_at' => now()->addHours(6), 'left_at' => now(), 'reason' => 'Indisponibilité'];
    }
}
