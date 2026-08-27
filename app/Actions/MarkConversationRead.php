<?php

namespace App\Actions;

use App\Events\MessagesRead;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class MarkConversationRead
{
    public function handle(User $reader, Conversation $conversation): void
    {
        Gate::forUser($reader)->authorize('view', $conversation);

        DB::transaction(function () use ($reader, $conversation): void {
            $query = $conversation->messages()
                ->where('author_user_id', '!=', $reader->id)
                ->whereNull('read_at');
            $lastReadMessageId = (int) ((clone $query)->max('id') ?? 0);

            if ($lastReadMessageId === 0) {
                return;
            }

            $readAt = now();
            $query->update(['read_at' => $readAt]);

            MessagesRead::dispatch(
                $conversation->id,
                $reader->id,
                $lastReadMessageId,
                $readAt->toISOString(),
            );
        });
    }
}
