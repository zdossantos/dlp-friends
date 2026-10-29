<?php

namespace App\Enums;

enum MemberFeedbackKind: string
{
    case Idea = 'idea';
    case Problem = 'problem';
    case Other = 'other';
}
