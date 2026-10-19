<?php

namespace App\Notifications\Channels;

use App\Models\EventChat;
use App\Models\User;
use App\Notifications\NewGroupMessageNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\DB;

final class GroupMessageDatabaseChannel
{
    public function __construct(private readonly DatabaseChannel $database) {}

    public function send(User $notifiable, NewGroupMessageNotification $notification): void
    {
        DB::transaction(function () use ($notifiable, $notification): void {
            // Serialize delivery with MarkEventChatRead so an already-read message cannot reappear.
            EventChat::query()->lockForUpdate()->findOrFail($notification->message->event_chat_id);

            if ($notification->shouldSend($notifiable, self::class)) {
                $this->database->send($notifiable, $notification);
            }
        });
    }
}
