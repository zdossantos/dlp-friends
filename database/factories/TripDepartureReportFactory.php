<?php

namespace Database\Factories;

use App\Models\TripDepartureReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TripDepartureReport> */
class TripDepartureReportFactory extends Factory
{
    protected $model = TripDepartureReport::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['reporter_user_id' => User::factory(), 'target_user_id' => User::factory(), 'scheduled_departure_at' => now()->addHour(), 'left_at' => now(), 'details' => fake()->sentence()];
    }
}
