<?php

namespace App\Models;

use Database\Factories\EventChatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $event_id
 * @property-read Event $event
 * @property-read Collection<int, EventChatMessage> $messages
 * @property-read Collection<int, EventChatRead> $reads
 */
#[Fillable(['event_id'])]
class EventChat extends Model
{
    /** @use HasFactory<EventChatFactory> */
    use HasFactory;

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<EventChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(EventChatMessage::class);
    }

    /** @return HasMany<EventChatRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(EventChatRead::class);
    }

    public function isReadOnly(): bool
    {
        return $this->event->cancelled_at !== null
            || now()->greaterThanOrEqualTo($this->event->starts_at->addDays(7));
    }

    public function readOnlyReason(): ?string
    {
        if ($this->event->cancelled_at !== null) {
            return 'cancelled';
        }

        return now()->greaterThanOrEqualTo($this->event->starts_at->addDays(7))
            ? 'expired'
            : null;
    }
}
