<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $max_selections
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['max_selections'])]
class InterestSetting extends Model
{
    public static function current(): self
    {
        $now = now();

        self::query()->insertOrIgnore([
            'id' => 1,
            'max_selections' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return self::query()
            ->useWritePdo()
            ->whereKey(1)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['max_selections' => 'integer'];
    }
}
