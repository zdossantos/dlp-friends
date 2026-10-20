<?php

namespace App\Actions;

use App\Enums\EventChatSystemType;
use App\Events\EventChatMessageSent;
use App\Models\EventChatMessage;
use App\Models\EventRegistration;

final class AnnounceEventChatMembership
{
    public function handle(EventRegistration $registration, EventChatSystemType $type): EventChatMessage
    {
        $chat = $registration->event->chat()->firstOrCreate();
        $message = $chat->messages()->create([
            'author_user_id' => $registration->user_id,
            'content' => '',
            'system_type' => $type,
        ]);

        EventChatMessageSent::dispatch($message);

        return $message;
    }
}
