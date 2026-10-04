<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\ConversationReport;
use App\Models\User;

final class ConversationReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->hasRole('admin');
    }

    public function view(User $user, ConversationReport $report): bool
    {
        return $this->viewAny($user);
    }

    public function close(User $user, ConversationReport $report): bool
    {
        return $this->view($user, $report) && $report->closed_at === null;
    }
}
