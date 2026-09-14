<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_chat_id
 * @property int $user_id
 * @property int|null $last_read_message_id
 * @property-read EventChat $eventChat
 * @property-read User $user
 * @property-read EventChatMessage|null $lastReadMessage
 */
#[Fillable(['event_chat_id', 'user_id', 'last_read_message_id'])]
class EventChatRead extends Model
{
    /** @return BelongsTo<EventChat, $this> */
    public function eventChat(): BelongsTo
    {
        return $this->belongsTo(EventChat::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<EventChatMessage, $this> */
    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(EventChatMessage::class, 'last_read_message_id');
    }
}
