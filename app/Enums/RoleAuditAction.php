<?php

namespace App\Enums;

enum RoleAuditAction: string
{
    case Assigned = 'assigned';
    case Removed = 'removed';
}
