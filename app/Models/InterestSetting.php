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
        return self::unguarded(
            fn (): self => self::query()->firstOrCreate(
                ['id' => 1],
                ['max_selections' => 5],
            ),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['max_selections' => 'integer'];
    }
}
