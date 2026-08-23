<?php

namespace App\Models;

use App\Enums\SwipeDecision;
use Database\Factories\SwipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $actor_user_id
 * @property int $target_user_id
 * @property SwipeDecision $decision
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $actor
 * @property-read User $target
 */
#[Fillable(['actor_user_id', 'target_user_id', 'decision'])]
class Swipe extends Model
{
    /** @use HasFactory<SwipeFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'decision' => SwipeDecision::class,
        ];
    }
}
