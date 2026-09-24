<?php

namespace App\Models;

use App\Enums\PartnerRevisionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PartnerProfileRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $partner_profile_id
 * @property string $name_fr
 * @property string $name_en
 * @property string $description_fr
 * @property string $description_en
 * @property string|null $image_path
 * @property PartnerRevisionStatus $status
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $decided_at
 * @property int|null $decided_by
 * @property string|null $rejection_reason
 * @property int|null $draft_key
 * @property CarbonImmutable|null $expires_at
 * @property-read PartnerProfile $partnerProfile
 * @property-read User|null $decider
 */
#[Fillable([
    'partner_profile_id',
    'name_fr',
    'name_en',
    'description_fr',
    'description_en',
    'image_path',
    'status',
    'submitted_at',
    'decided_at',
    'decided_by',
    'rejection_reason',
    'draft_key',
    'expires_at',
])]
class PartnerProfileRevision extends Model
{
    /** @use HasFactory<PartnerProfileRevisionFactory> */
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PartnerRevisionStatus::class,
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
