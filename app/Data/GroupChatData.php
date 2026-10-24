<?php

namespace App\Data;

use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\User;
use Inertia\Inertia;

final readonly class GroupChatData
{
    /** @return array<string, mixed> */
    public static function conversation(GroupChat $chat, User $viewer): array
    {
        return [
            'chat' => fn (): array => self::chat($chat),
            'messageUpdates' => Inertia::optional(fn (): array => self::updates($chat, $viewer, request()->integer('messages_after'))),
            'messages' => Inertia::scroll(function () use ($chat, $viewer) {
                $messages = $chat->messages()
                    ->with('author.profile')
                    ->withCount('reactions')
                    ->withViewerReaction($viewer)
                    ->orderByDesc('id')
                    ->simplePaginate(10, ['*'], 'messages')
                    ->through(fn (GroupChatMessage $message): array => self::message($message, $viewer));
                $messages->setCollection($messages->getCollection()->reverse()->values());

                return $messages;
            }),
        ];
    }

    /** @return array<string, mixed> */
    public static function chat(GroupChat $chat): array
    {
        $reason = $chat->readOnlyReason($chat->trip ?? $chat->event);

        return [
            'id' => $chat->id,
            'isReadOnly' => $reason !== null,
            'readOnlyReason' => $reason,
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
                ? (bool) ($message->getAttribute('reacted_by_viewer') ?? $message->reactions()->where('user_id', $viewer->id)->exists())
                : false,
            'author' => [
                'id' => $message->author->id,
                'display_name' => $message->author->profile?->display_name,
            ],
            'created_at' => $message->created_at?->toISOString(),
            'updated_at' => $message->updated_at?->toISOString(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function updates(GroupChat $chat, User $viewer, int $after): array
    {
        return $chat->messages()
            ->with('author.profile')->withCount('reactions')
            ->withViewerReaction($viewer)
            ->where('id', '>', max($after, 0))->orderBy('id')->limit(100)
            ->get()->map(fn (GroupChatMessage $message): array => self::message($message, $viewer))->all();
    }

    public static function unreadCount(GroupChat $chat, User $viewer): int
    {
        if ($chat->getAttribute('unread_count') !== null) {
            return (int) $chat->getAttribute('unread_count');
        }
        $lastReadMessageId = (int) ($chat->reads()
            ->where('user_id', $viewer->id)
            ->value('last_read_message_id') ?? 0);

        return $chat->messages()
            ->where('author_user_id', '!=', $viewer->id)
            ->where('id', '>', $lastReadMessageId)
            ->count();
    }
}
