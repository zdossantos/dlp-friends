<?php

namespace App\Models;

use Database\Factories\GroupChatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $event_id
 * @property int|null $trip_id
 * @property-read Event|null $event
 * @property-read Trip|null $trip
 * @property-read Collection<int, GroupChatMessage> $messages
 * @property-read Collection<int, GroupChatRead> $reads
 */
#[Fillable(['event_id', 'trip_id'])]
class GroupChat extends Model
{
    /** @use HasFactory<GroupChatFactory> */
    use HasFactory;

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return HasMany<GroupChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(GroupChatMessage::class);
    }

    /** @return HasMany<GroupChatRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(GroupChatRead::class);
    }

    public function isReadOnly(): bool
    {
        return $this->readOnlyReason() !== null;
    }

    public function readOnlyReason(): ?string
    {
        $context = $this->trip()->first() ?? $this->event()->first();
        if ($context === null || $context->cancelled_at !== null) {
            return 'cancelled';
        }

        $expires = $context instanceof Trip ? $context->departs_at->addHours(24) : $context->starts_at->addDays(7);

        return now()->greaterThanOrEqualTo($expires) ? 'expired' : null;
    }
}
