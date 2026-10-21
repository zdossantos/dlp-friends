<?php

namespace App\Notifications\Channels;

use App\Events\GroupMessageNotificationBroadcast;
use App\Models\User;
use App\Notifications\GroupChatMessageLikedNotification;
use App\Notifications\NewGroupMessageNotification;
use App\Notifications\TripLifecycleNotification;

final class GroupMessageBroadcastChannel
{
    public function send(User $notifiable, NewGroupMessageNotification|GroupChatMessageLikedNotification|TripLifecycleNotification $notification): void
    {
        if ($notification->shouldSend($notifiable, self::class)) {
            event(new GroupMessageNotificationBroadcast($notifiable->id, $notification));
        }
    }
}
