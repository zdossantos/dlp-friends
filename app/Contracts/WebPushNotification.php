<?php

namespace App\Contracts;

use App\Enums\WebPushPreference;
use App\Models\User;
use App\Support\WebPushTarget;

interface WebPushNotification
{
    public function webPushPreference(): WebPushPreference;

    public function webPushTarget(User $notifiable): WebPushTarget;

    public function webPushAccessAllowed(User $notifiable): bool;
}
