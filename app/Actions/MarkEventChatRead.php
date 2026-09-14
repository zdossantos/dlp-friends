<?php

namespace App\Actions;

use App\Models\EventChat;
use App\Models\EventChatRead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class MarkEventChatRead
{
    public function handle(User $reader, EventChat $chat, int $messageId): void
    {
        Gate::forUser($reader)->authorize('view', $chat);

        DB::transaction(function () use ($reader, $chat, $messageId): void {
            EventChat::query()->lockForUpdate()->findOrFail($chat->id);

            $read = EventChatRead::query()->firstOrNew([
                'event_chat_id' => $chat->id,
                'user_id' => $reader->id,
            ]);

            if ((int) ($read->last_read_message_id ?? 0) >= $messageId) {
                return;
            }

            $read->last_read_message_id = $messageId;
            $read->save();
        });
    }
}
