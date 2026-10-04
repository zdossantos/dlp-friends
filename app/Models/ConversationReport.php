<?php

namespace App\Models;

use App\Enums\ConversationReportReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $reporter_user_id
 * @property int|null $target_user_id
 * @property int|null $decided_by_user_id
 * @property ConversationReportReason $reason
 * @property string|null $details
 * @property string|null $decision
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property-read Conversation $conversation
 * @property-read User|null $reporter
 * @property-read User|null $target
 */
#[Fillable(['conversation_id', 'reporter_user_id', 'target_user_id', 'reason', 'details', 'open_key', 'decided_by_user_id', 'decision', 'closed_at'])]
class ConversationReport extends Model
{
    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['reason' => ConversationReportReason::class, 'closed_at' => 'datetime'];
    }
}
