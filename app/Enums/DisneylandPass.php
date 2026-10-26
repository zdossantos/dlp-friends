<?php

namespace App\Enums;

enum DisneylandPass: string
{
    case BronzeOne = 'bronze_one';
    case BronzeMax = 'bronze_max';
    case Silver = 'silver';
    case Gold = 'gold';
    case Bronze = 'bronze';

    public function label(): string
    {
        return __('profile.pass.options.'.$this->value);
    }
}
