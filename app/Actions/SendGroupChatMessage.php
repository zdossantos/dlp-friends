<?php

namespace App\Actions;

use App\Events\EventChatMessageSent;
use App\Models\GroupChat;
use App\Models\GroupChatMessage;
use App\Models\User;
use App\Notifications\NewGroupMessageNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SendGroupChatMessage
{
    public function handle(User $author, GroupChat $chat, string $content): GroupChatMessage
    {
        return DB::transaction(function () use ($author, $chat, $content): GroupChatMessage {
            $lockedChat = GroupChat::query()
                ->with('event')
                ->lockForUpdate()
                ->findOrFail($chat->id);

            Gate::forUser($author)->authorize('send', $lockedChat);

            $message = $lockedChat->messages()->create([
                'author_user_id' => $author->id,
                'content' => $content,
            ]);

            EventChatMessageSent::dispatch($message);

            $recipients = User::query()->inGroupChat($lockedChat)->whereKeyNot($author->id)->get();

            foreach ($recipients as $recipient) {
                $recipient->notify(new NewGroupMessageNotification($message));
            }

            return $message;
        });
    }
}
