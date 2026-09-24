<?php

namespace App\Enums;

enum SeasonalThemeName: string
{
    case Halloween = 'halloween';
    case Christmas = 'christmas';

    public function schedulePriority(): int
    {
        return match ($this) {
            self::Halloween => 1,
            self::Christmas => 2,
        };
    }
}
