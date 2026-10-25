<?php

namespace App\Models;

use App\Enums\EventChatSystemType;
use Carbon\CarbonImmutable;
use Database\Factories\GroupChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $id
 * @property int $group_chat_id
 * @property int $author_user_id
 * @property EventChatSystemType|null $system_type
 * @property string $content
 * @property-read GroupChat $groupChat
 * @property-read User $author
 * @property int $reactions_count
 */
#[Fillable(['group_chat_id', 'author_user_id', 'content', 'system_type'])]
class GroupChatMessage extends Model
{
    /** @use HasFactory<GroupChatMessageFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['system_type' => EventChatSystemType::class];
    }

    /** @return BelongsTo<GroupChat, $this> */
    public function groupChat(): BelongsTo
    {
        return $this->belongsTo(GroupChat::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /** @return HasMany<GroupChatMessageReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(GroupChatMessageReaction::class);
    }

    /** @param Builder<GroupChatMessage> $query */
    public function scopeWithViewerReaction(Builder $query, User $viewer): void
    {
        $query->withExists(['reactions as reacted_by_viewer' => fn (Builder $reactions) => $reactions->where('user_id', $viewer->id)]);
    }
}
