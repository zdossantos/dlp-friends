<?php

namespace App\Actions;

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

        return DB::transaction(fn (): Message => $conversation->messages()->create([
            'author_user_id' => $author->id,
            'content' => $content,
        ]));
    }
}
