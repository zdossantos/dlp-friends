<?php

namespace App\Models;

use App\Enums\EventChatSystemType;
use Database\Factories\EventChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $event_chat_id
 * @property int $author_user_id
 * @property EventChatSystemType|null $system_type
 * @property string $content
 * @property-read EventChat $eventChat
 * @property-read User $author
 * @property int $reactions_count
 */
#[Fillable(['event_chat_id', 'author_user_id', 'content', 'system_type'])]
class EventChatMessage extends Model
{
    /** @use HasFactory<EventChatMessageFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['system_type' => EventChatSystemType::class];
    }

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

    /** @return HasMany<EventChatMessageReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(EventChatMessageReaction::class);
    }
}
