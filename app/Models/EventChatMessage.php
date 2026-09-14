<?php

namespace App\Models;

use Database\Factories\EventChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_chat_id
 * @property int $author_user_id
 * @property string $content
 * @property-read EventChat $eventChat
 * @property-read User $author
 */
#[Fillable(['event_chat_id', 'author_user_id', 'content'])]
class EventChatMessage extends Model
{
    /** @use HasFactory<EventChatMessageFactory> */
    use HasFactory;

    /** @return BelongsTo<EventChat, $this> */
    public function eventChat(): BelongsTo
    {
        return $this->belongsTo(EventChat::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
