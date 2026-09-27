<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebPushSubscription> */
class WebPushSubscriptionFactory extends Factory
{
    protected $model = WebPushSubscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'endpoint' => 'https://push.example.test/'.fake()->uuid(),
            'p256dh' => fake()->regexify('[A-Za-z0-9_-]{88}'),
            'auth' => fake()->regexify('[A-Za-z0-9_-]{22}'),
            'device_name' => fake()->words(2, true),
            'platform' => 'web',
        ];
    }
}
