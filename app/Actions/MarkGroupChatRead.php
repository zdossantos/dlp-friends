<?php

namespace App\Actions;

use App\Models\GroupChat;
use App\Models\GroupChatRead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class MarkGroupChatRead
{
    public function handle(User $reader, GroupChat $chat, int $messageId): void
    {
        Gate::forUser($reader)->authorize('view', $chat);

        DB::transaction(function () use ($reader, $chat, $messageId): void {
            $chat = GroupChat::query()->lockForUpdate()->findOrFail($chat->id);
            Gate::forUser($reader)->authorize('view', $chat);
            $chat->messages()->findOrFail($messageId);

            $reader->unreadNotifications()
                ->where('data->target_type', $chat->trip_id !== null ? 'trip_chat' : 'event_chat')
                ->where('data->target_id', $chat->trip_id ?? $chat->event_id)
                ->where('data->message_id', '<=', $messageId)
                ->update(['read_at' => now()]);

            $read = GroupChatRead::query()->firstOrNew([
                'group_chat_id' => $chat->id,
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
