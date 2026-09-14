<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $partner_announcement_id
 * @property int $prepared_count
 * @property int $delivered_count
 * @property int $read_count
 * @property int $dismissed_count
 * @property int $unique_click_count
 * @property int $total_click_count
 * @property CarbonImmutable|null $expires_at
 * @property-read PartnerAnnouncement $announcement
 */
#[Fillable([
    'partner_announcement_id',
    'prepared_count',
    'delivered_count',
    'read_count',
    'dismissed_count',
    'unique_click_count',
    'total_click_count',
    'expires_at',
])]
class PartnerAnnouncementMetric extends Model
{
    /** @return BelongsTo<PartnerAnnouncement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(PartnerAnnouncement::class, 'partner_announcement_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'prepared_count' => 'integer',
            'delivered_count' => 'integer',
            'read_count' => 'integer',
            'dismissed_count' => 'integer',
            'unique_click_count' => 'integer',
            'total_click_count' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
