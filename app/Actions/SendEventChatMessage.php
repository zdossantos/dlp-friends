<?php

namespace App\Actions;

use App\Events\EventChatMessageSent;
use App\Models\EventChat;
use App\Models\EventChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SendEventChatMessage
{
    public function handle(User $author, EventChat $chat, string $content): EventChatMessage
    {
        return DB::transaction(function () use ($author, $chat, $content): EventChatMessage {
            $lockedChat = EventChat::query()
                ->with('event')
                ->lockForUpdate()
                ->findOrFail($chat->id);

            Gate::forUser($author)->authorize('send', $lockedChat);

            $message = $lockedChat->messages()->create([
                'author_user_id' => $author->id,
                'content' => $content,
            ]);

            EventChatMessageSent::dispatch($message);

            return $message;
        });
    }
}
