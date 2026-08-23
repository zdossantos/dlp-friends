<?php

namespace Database\Factories;

use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberMatch> */
class MemberMatchFactory extends Factory
{
    protected $model = MemberMatch::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        [$lowId, $highId] = [$first->id, $second->id];

        if ($lowId > $highId) {
            [$lowId, $highId] = [$highId, $lowId];
        }

        return [
            'user_low_id' => $lowId,
            'user_high_id' => $highId,
        ];
    }
}
