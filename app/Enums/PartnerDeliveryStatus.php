<?php

namespace App\Enums;

enum PartnerDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
