<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Visit> */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory()->withProfile(), 'starts_on' => today('Europe/Paris')->addDay()->toDateString(), 'ends_on' => today('Europe/Paris')->addDays(3)->toDateString()];
    }
}
