<?php

namespace App\Contracts;

use App\Models\User;

interface PersonalizedWebPushNotification
{
    /** @return array{title: string, body: string} */
    public function webPushCopy(User $notifiable, string $locale): array;
}
