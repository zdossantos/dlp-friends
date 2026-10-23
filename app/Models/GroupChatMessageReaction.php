<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_chat_message_id', 'user_id'])]
class GroupChatMessageReaction extends Model
{
    /** @return BelongsTo<GroupChatMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(GroupChatMessage::class, 'group_chat_message_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
