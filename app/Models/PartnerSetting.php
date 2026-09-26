<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $cooldown_days
 */
#[Fillable(['cooldown_days'])]
class PartnerSetting extends Model
{
    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['id' => 1],
            ['cooldown_days' => 30],
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cooldown_days' => 'integer'];
    }
}
