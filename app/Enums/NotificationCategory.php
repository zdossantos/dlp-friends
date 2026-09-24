<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Conversations = 'conversations';
    case Events = 'events';
    case Partners = 'partners';
    case Administration = 'administration';
}
