<?php

namespace Database\Factories;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventRegistration> */
class EventRegistrationFactory extends Factory
{
    protected $model = EventRegistration::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory()->withProfile(),
            'status' => EventRegistrationStatus::Pending,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => ['status' => EventRegistrationStatus::Accepted]);
    }
}
