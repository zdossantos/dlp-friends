<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $period_ends_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $skipped_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'period_ends_at', 'sent_at', 'skipped_at'])]
class WeeklyEmailRecapDelivery extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_ends_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'skipped_at' => 'immutable_datetime',
        ];
    }
}
