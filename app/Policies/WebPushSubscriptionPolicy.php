<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebPushSubscription;

class WebPushSubscriptionPolicy
{
    public function delete(User $user, WebPushSubscription $subscription): bool
    {
        return $subscription->user_id === $user->id;
    }
}
