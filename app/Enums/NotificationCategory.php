<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Conversations = 'conversations';
    case Events = 'events';
}
