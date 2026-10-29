<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\MemberFeedback;
use App\Models\User;

final class MemberFeedbackPolicy
{
    public function create(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->hasRole('user');
    }

    public function viewAny(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->hasRole('admin');
    }

    public function view(User $user, MemberFeedback $feedback): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MemberFeedback $feedback): bool
    {
        return $this->viewAny($user);
    }
}
