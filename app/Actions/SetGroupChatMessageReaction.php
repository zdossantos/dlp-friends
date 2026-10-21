<?php

namespace App\Actions;

use App\Events\EventChatMessageReactionUpdated;
use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\GroupChatMessageReaction;
use App\Models\User;
use App\Notifications\EventChatMessageLikedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class SetGroupChatMessageReaction
{
    /** @return array{message_id: int, reactor_user_id: int, reaction_count: int, reacted: bool} */
    public function handle(User $reactor, GroupChat $chat, GroupChatMessage $message, bool $reacted): array
    {
        return DB::transaction(function () use ($reactor, $chat, $message, $reacted): array {
            $lockedChat = GroupChat::query()->with('event')->lockForUpdate()->findOrFail($chat->id);
            Gate::forUser($reactor)->authorize('view', $lockedChat);

            $lockedMessage = GroupChatMessage::query()
                ->where('group_chat_id', $lockedChat->id)
                ->lockForUpdate()
                ->findOrFail($message->id);

            if ($lockedMessage->system_type !== null || ($reacted && $lockedMessage->author_user_id === $reactor->id)) {
                throw new AccessDeniedHttpException;
            }

            if ($reacted) {
                $reaction = GroupChatMessageReaction::query()->firstOrCreate([
                    'group_chat_message_id' => $lockedMessage->id,
                    'user_id' => $reactor->id,
                ]);

                if ($reaction->wasRecentlyCreated) {
                    $lockedMessage->author->notify(new EventChatMessageLikedNotification($lockedMessage, $reactor));
                }
            } else {
                GroupChatMessageReaction::query()
                    ->where('group_chat_message_id', $lockedMessage->id)
                    ->where('user_id', $reactor->id)
                    ->delete();
            }

            $payload = [
                'message_id' => $lockedMessage->id,
                'reactor_user_id' => $reactor->id,
                'reaction_count' => $lockedMessage->reactions()->count(),
                'reacted' => $reacted,
            ];

            EventChatMessageReactionUpdated::dispatch(
                $lockedChat->id,
                $payload['message_id'],
                $payload['reactor_user_id'],
                $payload['reaction_count'],
                $payload['reacted'],
            );

            return $payload;
        });
    }
}
