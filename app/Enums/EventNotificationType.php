<?php

namespace App\Enums;

enum EventNotificationType: string
{
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Removed = 'removed';
    case Changed = 'changed';
    case Cancelled = 'cancelled';
}
