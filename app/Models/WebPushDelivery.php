<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['notification_id', 'web_push_subscription_id', 'category', 'status', 'attempts', 'last_status_code', 'last_error', 'delivered_at'])]
class WebPushDelivery extends Model
{
    /** @return BelongsTo<WebPushSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebPushSubscription::class, 'web_push_subscription_id');
    }

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'last_status_code' => 'integer', 'delivered_at' => 'datetime'];
    }
}
