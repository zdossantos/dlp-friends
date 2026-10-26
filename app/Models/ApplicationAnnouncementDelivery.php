<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $broadcasted_at
 */
#[Fillable(['application_announcement_id', 'user_id', 'notification_id', 'status', 'attempts', 'delivered_at', 'broadcasted_at'])]
final class ApplicationAnnouncementDelivery extends Model
{
    /** @return BelongsTo<ApplicationAnnouncement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(ApplicationAnnouncement::class, 'application_announcement_id');
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
        return ['delivered_at' => 'immutable_datetime', 'broadcasted_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }
}
