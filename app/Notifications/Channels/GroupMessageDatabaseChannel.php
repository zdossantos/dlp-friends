<?php

namespace App\Notifications\Channels;

use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\GroupChatMessageLikedNotification;
use App\Notifications\NewGroupMessageNotification;
use App\Notifications\TripLifecycleNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\DB;

final class GroupMessageDatabaseChannel
{
    public function __construct(private readonly DatabaseChannel $database) {}

    public function send(User $notifiable, NewGroupMessageNotification|GroupChatMessageLikedNotification|TripLifecycleNotification $notification): void
    {
        DB::transaction(function () use ($notifiable, $notification): void {
            // Serialize delivery with MarkGroupChatRead so an already-read message cannot reappear.
            if ($notification instanceof TripLifecycleNotification) {
                $context = Trip::query()->lockForUpdate()->find($notification->tripId);
            } else {
                $chatId = GroupChatMessage::query()->whereKey($notification->messageId)->value('group_chat_id');
                $context = GroupChat::query()->lockForUpdate()->find($chatId);
            }
            if ($context === null) {
                return;
            }

            if ($notification->shouldSend($notifiable, self::class)
                && ! $notifiable->notifications()->whereKey($notification->id)->exists()) {
                $this->database->send($notifiable, $notification);
            }
        });
    }
}
