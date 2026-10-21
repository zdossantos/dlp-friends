<?php

namespace App\Enums;

enum TripDirection: string
{
    case Outbound = 'outbound';
    case Return = 'return';
}
