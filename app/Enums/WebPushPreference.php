<?php

namespace App\Enums;

enum WebPushPreference: string
{
    case Messages = 'messages';
    case GroupMessages = 'group_messages';
    case Matches = 'matches';
    case Events = 'events';
    case Trips = 'trips';
    case PartnerAnnouncements = 'partner_announcements';
    case Administration = 'administration';
}
