<?php

namespace App\Enums;

enum MemberFeedbackArea: string
{
    case Discovery = 'discovery';
    case Messaging = 'messaging';
    case Events = 'events';
    case Visits = 'visits';
    case Trips = 'trips';
    case Profile = 'profile';
    case Settings = 'settings';
    case Application = 'application';
    case Other = 'other';
}
