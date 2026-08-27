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
        Gate::forUser($author)->authorize('send', $conversation);

        return DB::transaction(function () use ($author, $conversation, $content): Message {
            $message = $conversation->messages()->create([
                'author_user_id' => $author->id,
                'content' => $content,
            ]);

            MessageSent::dispatch($message);

            return $message;
        });
    }
}
