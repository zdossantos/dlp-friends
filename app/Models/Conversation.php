<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_id
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MemberMatch $memberMatch
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Message> $messages
 */
#[Fillable(['match_id', 'archived_at'])]
class Conversation extends Model
{
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }
}
