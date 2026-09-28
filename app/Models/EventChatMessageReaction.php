<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_chat_message_id', 'user_id'])]
class EventChatMessageReaction extends Model
{
    /** @return BelongsTo<EventChatMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(EventChatMessage::class, 'event_chat_message_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
