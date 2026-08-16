<?php

namespace App\Enums;

enum VisitFrequency: string
{
    case Rarely = 'rarely';
    case Sometimes = 'sometimes';
    case Often = 'often';
    case VeryOften = 'very_often';

    public function label(): string
    {
        return match ($this) {
            self::Rarely => 'Rarement',
            self::Sometimes => 'De temps en temps',
            self::Often => 'Souvent',
            self::VeryOften => 'Très souvent',
        };
    }
}
