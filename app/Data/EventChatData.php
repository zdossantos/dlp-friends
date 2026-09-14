<?php

namespace App\Data;

use App\Models\EventChat;
use App\Models\EventChatMessage;
use App\Models\User;

final readonly class EventChatData
{
    /** @return array<string, mixed> */
    public static function chat(EventChat $chat): array
    {
        return [
            'id' => $chat->id,
            'isReadOnly' => $chat->isReadOnly(),
            'readOnlyReason' => $chat->readOnlyReason(),
        ];
    }

    /** @return array<string, mixed> */
    public static function message(EventChatMessage $message): array
    {
        $message->loadMissing('author.profile');

        return [
            'id' => $message->id,
            'event_chat_id' => $message->event_chat_id,
            'author_user_id' => $message->author_user_id,
            'content' => $message->content,
            'author' => [
                'id' => $message->author->id,
                'display_name' => $message->author->profile?->display_name,
            ],
            'created_at' => $message->created_at?->toISOString(),
            'updated_at' => $message->updated_at?->toISOString(),
        ];
    }

    public static function unreadCount(EventChat $chat, User $viewer): int
    {
        $lastReadMessageId = (int) ($chat->reads()
            ->where('user_id', $viewer->id)
            ->value('last_read_message_id') ?? 0);

        return $chat->messages()
            ->where('author_user_id', '!=', $viewer->id)
            ->where('id', '>', $lastReadMessageId)
            ->count();
    }
}
