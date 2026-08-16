<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case PendingDeletion = 'pending_deletion';
}
