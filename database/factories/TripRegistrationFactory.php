<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TripRegistration> */
class TripRegistrationFactory extends Factory
{
    protected $model = TripRegistration::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['trip_id' => Trip::factory(), 'user_id' => User::factory()->withProfile(), 'status' => 'pending'];
    }
}
