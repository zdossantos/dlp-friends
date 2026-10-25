<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\GroupChat;
use App\Models\Trip;
use App\Models\User;

/** Call inside the write transaction, before any snapshot reads. */
final class LockGroupChatForWriting
{
    public function handle(User $writer, Event|Trip $context, int $chatId): GroupChat
    {
        // Shared account locks block revocations while remaining compatible with membership announcement FKs.
        User::query()->whereKey([$writer->id, $context->organizer_user_id])
            ->orderBy('id')->sharedLock()->get();
        $context->newQuery()->whereKey($context->id)->lockForUpdate()->firstOrFail();

        return GroupChat::query()->lockForUpdate()->findOrFail($chatId);
    }
}
