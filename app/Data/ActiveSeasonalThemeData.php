<?php

namespace App\Data;

use App\Enums\SeasonalThemeName;
use Carbon\CarbonImmutable;

final readonly class ActiveSeasonalThemeData
{
    public function __construct(
        public ?SeasonalThemeName $active,
        public ?CarbonImmutable $nextTransitionAt,
    ) {}
}
