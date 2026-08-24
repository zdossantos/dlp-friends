<?php

namespace App\Models;

use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $display_name
 * @property string|null $bio
 * @property VisitFrequency|null $visit_frequency
 * @property ProfileVisibility $visibility
 * @property Carbon|null $onboarding_completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Interest> $interests
 * @property-read Collection<int, Interest> $interestHistory
 */
#[Fillable(['display_name', 'bio', 'visit_frequency', 'visibility', 'onboarding_completed_at'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Interest, $this> */
    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class)
            ->withPivot('is_selected')
            ->wherePivot('is_selected', true);
    }

    /** @return BelongsToMany<Interest, $this> */
    public function interestHistory(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class)->withPivot('is_selected');
    }

    public function isComplete(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visit_frequency' => VisitFrequency::class,
            'visibility' => ProfileVisibility::class,
            'onboarding_completed_at' => 'datetime',
        ];
    }
}
