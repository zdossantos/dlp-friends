<?php

namespace App\Actions;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SendMessage
{
    public function handle(User $author, Conversation $conversation, string $content): Message
    {
        return DB::transaction(function () use ($author, $conversation, $content): Message {
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

            Gate::forUser($author)->authorize('send', $lockedConversation);

            $message = $lockedConversation->messages()->create([
                'author_user_id' => $author->id,
                'content' => $content,
            ]);

            MessageSent::dispatch($message);

            return $message;
        });
    }
}
