<?php

namespace App\Enums;

enum VisitsVisibility: string
{
    case Hidden = 'hidden';
    case Matches = 'matches';
    case Members = 'members';
}
