<?php

namespace App\Models;

use App\Enums\ProfileVisibility;
use App\Enums\SwipeDecision;
use App\Enums\UserStatus;
use Database\Factories\SwipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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

    /** @param Builder<Swipe> $query */
    public function scopeAvailablePassesFor(Builder $query, User $viewer): void
    {
        $query->where('actor_user_id', $viewer->id)
            ->where('target_user_id', '!=', $viewer->id)
            ->where('decision', SwipeDecision::Pass)
            ->whereHas('target', function (Builder $target) use ($viewer): void {
                $target->where('status', UserStatus::Active)
                    ->where('birth_date', '<=', today()->subYears(18))
                    ->whereHas('profile', function (Builder $profile): void {
                        $profile->where('visibility', ProfileVisibility::Visible)
                            ->whereNotNull('onboarding_completed_at')
                            ->whereHas('avatar', fn (Builder $avatar) => $avatar->where('is_active', true));
                    })
                    ->whereDoesntHave('blocksReceived', fn (Builder $block) => $block->where('blocker_user_id', $viewer->id))
                    ->whereDoesntHave('blocksCreated', fn (Builder $block) => $block->where('blocked_user_id', $viewer->id));
            });
    }

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
