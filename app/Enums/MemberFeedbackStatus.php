<?php

namespace App\Enums;

enum MemberFeedbackStatus: string
{
    case Pending = 'pending';
    case Studying = 'studying';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Done = 'done';
}
