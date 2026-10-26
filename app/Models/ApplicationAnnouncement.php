<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $sent_at
 */
#[Fillable(['author_user_id', 'title_fr', 'title_en', 'content_fr', 'content_en', 'destination_url', 'is_release', 'send_push', 'audience', 'sent_at'])]
final class ApplicationAnnouncement extends Model
{
    /** @return HasMany<ApplicationAnnouncementDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ApplicationAnnouncementDelivery::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_release' => 'boolean', 'send_push' => 'boolean', 'sent_at' => 'immutable_datetime'];
    }
}
