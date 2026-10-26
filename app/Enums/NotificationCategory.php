<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Conversations = 'conversations';
    case Events = 'events';
    case Trips = 'trips';
    case Partners = 'partners';
    case Application = 'application';
    case Administration = 'administration';
}
