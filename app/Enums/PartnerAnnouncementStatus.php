<?php

namespace App\Enums;

enum PartnerAnnouncementStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Sending = 'sending';
    case Sent = 'sent';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
