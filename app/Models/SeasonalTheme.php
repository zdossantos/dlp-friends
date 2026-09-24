<?php

namespace App\Models;

use App\Enums\SeasonalThemeName;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property SeasonalThemeName $theme
 * @property bool $is_manually_active
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 */
#[Fillable(['theme', 'is_manually_active', 'starts_at', 'ends_at'])]
class SeasonalTheme extends Model
{
    public function getRouteKeyName(): string
    {
        return 'theme';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'theme' => SeasonalThemeName::class,
            'is_manually_active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }
}
