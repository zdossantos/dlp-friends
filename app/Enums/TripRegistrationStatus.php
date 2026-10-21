<?php

namespace App\Enums;

enum TripRegistrationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Withdrawn = 'withdrawn';
    case Removed = 'removed';
    case Blocked = 'blocked';
}
