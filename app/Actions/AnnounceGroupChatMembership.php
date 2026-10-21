<?php

namespace App\Actions;

use App\Enums\EventChatSystemType;
use App\Events\EventChatMessageSent;
use App\Models\EventRegistration;
use App\Models\GroupChatMessage;
use App\Models\TripRegistration;

final class AnnounceGroupChatMembership
{
    public function handle(EventRegistration|TripRegistration $registration, EventChatSystemType $type): GroupChatMessage
    {
        $context = $registration instanceof TripRegistration ? $registration->trip : $registration->event;
        $chat = $context->chat()->firstOrCreate();
        $message = $chat->messages()->create([
            'author_user_id' => $registration->user_id,
            'content' => '',
            'system_type' => $type,
        ]);

        EventChatMessageSent::dispatch($message);

        return $message;
    }
}
