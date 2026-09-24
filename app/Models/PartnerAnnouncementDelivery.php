<?php

namespace App\Models;

use App\Enums\PartnerDeliveryStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PartnerAnnouncementDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @property int $id
 * @property int|null $partner_announcement_id
 * @property int $source_announcement_id
 * @property string $announcement_title
 * @property string $announcement_content
 * @property string $announcement_destination_url
 * @property int $user_id
 * @property string|null $notification_id
 * @property string $click_token
 * @property PartnerDeliveryStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $broadcasted_at
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $dismissed_at
 * @property CarbonImmutable|null $first_clicked_at
 * @property int $click_count
 * @property-read PartnerAnnouncement|null $announcement
 * @property-read User $user
 * @property-read DatabaseNotification|null $notification
 */
#[Fillable([
    'partner_announcement_id',
    'source_announcement_id',
    'announcement_title',
    'announcement_content',
    'announcement_destination_url',
    'user_id',
    'notification_id',
    'click_token',
    'status',
    'attempts',
    'last_error',
    'delivered_at',
    'broadcasted_at',
    'read_at',
    'dismissed_at',
    'first_clicked_at',
    'click_count',
])]
class PartnerAnnouncementDelivery extends Model
{
    /** @use HasFactory<PartnerAnnouncementDeliveryFactory> */
    use HasFactory;

    /** @return BelongsTo<PartnerAnnouncement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(PartnerAnnouncement::class, 'partner_announcement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<DatabaseNotification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(DatabaseNotification::class, 'notification_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PartnerDeliveryStatus::class,
            'attempts' => 'integer',
            'delivered_at' => 'immutable_datetime',
            'broadcasted_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
            'first_clicked_at' => 'immutable_datetime',
            'click_count' => 'integer',
        ];
    }
}
