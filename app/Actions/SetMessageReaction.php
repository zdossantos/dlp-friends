<?php

namespace App\Actions;

use App\Events\MessageReactionUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Notifications\MessageLikedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class SetMessageReaction
{
    /** @return array{message_id: int, reactor_user_id: int, reaction_count: int, reacted: bool} */
    public function handle(User $reactor, Conversation $conversation, Message $message, bool $reacted): array
    {
        return DB::transaction(function () use ($reactor, $conversation, $message, $reacted): array {
            $conversation->loadMissing('memberMatch');
            $match = $conversation->memberMatch;

            User::query()
                ->whereKey([$match->user_low_id, $match->user_high_id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $lockedConversation = Conversation::query()
                ->with('memberMatch.lowUser', 'memberMatch.highUser')
                ->lockForUpdate()
                ->findOrFail($conversation->id);
            Gate::forUser($reactor)->authorize('send', $lockedConversation);

            $lockedMessage = Message::query()
                ->where('conversation_id', $lockedConversation->id)
                ->lockForUpdate()
                ->findOrFail($message->id);

            if ($reacted && $lockedMessage->author_user_id === $reactor->id) {
                throw new AccessDeniedHttpException;
            }

            if ($reacted) {
                $reaction = MessageReaction::query()->firstOrCreate([
                    'message_id' => $lockedMessage->id,
                    'user_id' => $reactor->id,
                ]);

                if ($reaction->wasRecentlyCreated && $lockedMessage->author_user_id !== $reactor->id) {
                    $lockedMessage->author->notify(new MessageLikedNotification($lockedMessage, $reactor));
                }
            } else {
                MessageReaction::query()
                    ->where('message_id', $lockedMessage->id)
                    ->where('user_id', $reactor->id)
                    ->delete();
            }

            $payload = [
                'message_id' => $lockedMessage->id,
                'reactor_user_id' => $reactor->id,
                'reaction_count' => $lockedMessage->reactions()->count(),
                'reacted' => $reacted,
            ];

            MessageReactionUpdated::dispatch(
                $lockedConversation->id,
                $payload['message_id'],
                $payload['reactor_user_id'],
                $payload['reaction_count'],
                $payload['reacted'],
            );

            return $payload;
        });
    }
}
