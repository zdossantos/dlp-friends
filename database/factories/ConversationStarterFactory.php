<?php

namespace Database\Factories;

use App\Models\ConversationStarter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConversationStarter> */
class ConversationStarterFactory extends Factory
{
    protected $model = ConversationStarter::class;

    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 65000);

        return [
            'text_fr' => "FR question {$number}",
            'text_en' => "EN question {$number}",
            'is_active' => true,
            'sort_order' => $number,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
