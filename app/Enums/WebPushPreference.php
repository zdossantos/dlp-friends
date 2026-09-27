<?php

namespace App\Enums;

enum WebPushPreference: string
{
    case Messages = 'messages';
    case Matches = 'matches';
    case Events = 'events';
    case PartnerAnnouncements = 'partner_announcements';
    case Administration = 'administration';
}
