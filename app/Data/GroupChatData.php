<?php

namespace App\Data;

use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\User;

final readonly class GroupChatData
{
    /** @return array<string, mixed> */
    public static function chat(GroupChat $chat): array
    {
        return [
            'id' => $chat->id,
            'isReadOnly' => $chat->isReadOnly(),
            'readOnlyReason' => $chat->readOnlyReason(),
        ];
    }

    /** @return array<string, mixed> */
    public static function message(GroupChatMessage $message, ?User $viewer = null): array
    {
        $message->loadMissing('author.profile');

        return [
            'id' => $message->id,
            'group_chat_id' => $message->group_chat_id,
            'author_user_id' => $message->author_user_id,
            'content' => $message->content,
            'system_type' => $message->system_type?->value,
            'reaction_count' => $message->reactions_count ?? $message->reactions()->count(),
            'reacted_by_current_user' => $viewer !== null
                ? $message->reactions()->where('user_id', $viewer->id)->exists()
                : false,
            'author' => [
                'id' => $message->author->id,
                'display_name' => $message->author->profile?->display_name,
            ],
            'created_at' => $message->created_at?->toISOString(),
            'updated_at' => $message->updated_at?->toISOString(),
        ];
    }

    public static function unreadCount(GroupChat $chat, User $viewer): int
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
