<?php

namespace App\Enums;

enum ConversationReportReason: string
{
    case Harassment = 'harassment';
    case Discrimination = 'discrimination';
    case SexualContent = 'sexual_content';
    case Threats = 'threats';
    case Spam = 'spam';
    case Other = 'other';
}
