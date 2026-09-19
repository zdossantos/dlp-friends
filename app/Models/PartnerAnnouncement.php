<?php

namespace App\Models;

use App\Enums\PartnerAnnouncementStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PartnerAnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $partner_profile_id
 * @property string $title
 * @property string $content
 * @property string $destination_url
 * @property PartnerAnnouncementStatus $status
 * @property CarbonImmutable|null $submitted_at
 * @property string|null $run_uuid
 * @property CarbonImmutable|null $audience_prepared_at
 * @property CarbonImmutable|null $sending_started_at
 * @property CarbonImmutable|null $sent_at
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $rejection_reason
 * @property CarbonImmutable|null $expires_at
 * @property-read PartnerProfile $partnerProfile
 * @property-read User|null $decider
 * @property-read Collection<int, PartnerAnnouncementDelivery> $deliveries
 * @property-read PartnerAnnouncementMetric|null $metric
 */
#[Fillable([
    'partner_profile_id',
    'title',
    'content',
    'destination_url',
    'status',
    'submitted_at',
    'run_uuid',
    'audience_prepared_at',
    'sending_started_at',
    'sent_at',
    'decided_by',
    'decided_at',
    'rejection_reason',
    'expires_at',
])]
class PartnerAnnouncement extends Model
{
    /** @use HasFactory<PartnerAnnouncementFactory> */
    use HasFactory;

    /** @return BelongsTo<PartnerProfile, $this> */
    public function partnerProfile(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return HasMany<PartnerAnnouncementDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(PartnerAnnouncementDelivery::class);
    }

    /** @return HasOne<PartnerAnnouncementMetric, $this> */
    public function metric(): HasOne
    {
        return $this->hasOne(PartnerAnnouncementMetric::class);
    }

    /** @param Builder<PartnerAnnouncement> $query */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->whereHas(
            'partnerProfile',
            fn (Builder $profiles) => $profiles->where('user_id', $user->id),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PartnerAnnouncementStatus::class,
            'submitted_at' => 'immutable_datetime',
            'audience_prepared_at' => 'immutable_datetime',
            'sending_started_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
