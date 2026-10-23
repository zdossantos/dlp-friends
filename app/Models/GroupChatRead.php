<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_chat_id
 * @property int $user_id
 * @property int|null $last_read_message_id
 * @property-read GroupChat $groupChat
 * @property-read User $user
 * @property-read GroupChatMessage|null $lastReadMessage
 */
#[Fillable(['group_chat_id', 'user_id', 'last_read_message_id'])]
class GroupChatRead extends Model
{
    /** @return BelongsTo<GroupChat, $this> */
    public function groupChat(): BelongsTo
    {
        return $this->belongsTo(GroupChat::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<GroupChatMessage, $this> */
    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(GroupChatMessage::class, 'last_read_message_id');
    }
}
