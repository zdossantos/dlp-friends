<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_id
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MemberMatch $memberMatch
 * @property-read Collection<int, Message> $messages
 * @property-read Message|null $latestMessage
 */
#[Fillable(['match_id', 'archived_at'])]
class Conversation extends Model
{
    /** @param Builder<Conversation> $query */
    public function scopeForMember(Builder $query, User $user): void
    {
        $query->whereHas('memberMatch', fn (Builder $match) => $match
            ->where('user_low_id', $user->id)
            ->orWhere('user_high_id', $user->id));
    }

    /** @return BelongsTo<MemberMatch, $this> */
    public function memberMatch(): BelongsTo
    {
        return $this->belongsTo(MemberMatch::class, 'match_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasOne<Message, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }
}
