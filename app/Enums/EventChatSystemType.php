<?php

namespace App\Enums;

enum EventChatSystemType: string
{
    case Joined = 'joined';
    case Left = 'left';
    case Removed = 'removed';
}
