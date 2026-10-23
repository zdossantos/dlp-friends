<?php

namespace App\Enums;

enum TripNotificationType: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Withdrawn = 'withdrawn';
    case Removed = 'removed';
    case Changed = 'changed';
    case Cancelled = 'cancelled';
}
