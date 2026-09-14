<?php

namespace App\Models;

use Database\Factories\PartnerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $published_revision_id
 * @property bool $is_published
 * @property int $position
 * @property-read User|null $user
 * @property-read PartnerProfileRevision|null $publishedRevision
 * @property-read Collection<int, PartnerProfileRevision> $revisions
 * @property-read Collection<int, PartnerAnnouncement> $announcements
 */
#[Fillable(['user_id', 'published_revision_id', 'is_published', 'position'])]
class PartnerProfile extends Model
{
    /** @use HasFactory<PartnerProfileFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PartnerProfileRevision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(PartnerProfileRevision::class, 'published_revision_id');
    }

    /** @return HasMany<PartnerProfileRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PartnerProfileRevision::class);
    }

    /** @return HasMany<PartnerAnnouncement, $this> */
    public function announcements(): HasMany
    {
        return $this->hasMany(PartnerAnnouncement::class);
    }

    /** @param Builder<PartnerProfile> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->whereNotNull('published_revision_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'position' => 'integer',
        ];
    }
}
