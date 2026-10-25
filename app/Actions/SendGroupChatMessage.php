<?php

namespace App\Actions;

use App\Events\GroupChatMessageSent;
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
        $context = $chat->trip_id !== null ? $chat->trip()->firstOrFail() : $chat->event()->firstOrFail();

        return DB::transaction(function () use ($author, $chat, $context, $content): GroupChatMessage {
            $lockedChat = app(LockGroupChatForWriting::class)->handle($author, $context, $chat->id);

            Gate::forUser($author)->authorize('send', $lockedChat);

            $message = $lockedChat->messages()->create([
                'author_user_id' => $author->id,
                'content' => $content,
            ]);

            GroupChatMessageSent::dispatch($message);

            $recipients = User::query()->inGroupChat($lockedChat)->whereKeyNot($author->id)->get();

            foreach ($recipients as $recipient) {
                $recipient->notify(new NewGroupMessageNotification($message));
            }

            return $message;
        });
    }
}
