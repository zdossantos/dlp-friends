<?php

namespace App\Models;

use App\Enums\MemberFeedbackArea;
use App\Enums\MemberFeedbackKind;
use App\Enums\MemberFeedbackStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property MemberFeedbackKind $kind
 * @property MemberFeedbackArea $area
 * @property string $content
 * @property MemberFeedbackStatus $status
 * @property string|null $internal_note
 * @property Carbon|null $created_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'kind', 'area', 'content', 'status', 'internal_note'])]
class MemberFeedback extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => MemberFeedbackKind::class, 'area' => MemberFeedbackArea::class, 'status' => MemberFeedbackStatus::class];
    }
}
