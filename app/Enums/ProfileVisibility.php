<?php

namespace App\Enums;

enum ProfileVisibility: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Visible => 'Visible',
            self::Hidden => 'Masqué',
        };
    }
}
