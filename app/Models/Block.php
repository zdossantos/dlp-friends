<?php

namespace App\Models;

use Database\Factories\BlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $blocker_user_id
 * @property int $blocked_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $blocker
 * @property-read User $blocked
 */
#[Fillable(['blocker_user_id', 'blocked_user_id'])]
class Block extends Model
{
    /** @use HasFactory<BlockFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }
}
