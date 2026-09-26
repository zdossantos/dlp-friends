<?php

namespace App\Models;

use Database\Factories\WebPushSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'endpoint', 'p256dh', 'auth', 'content_encoding', 'device_name', 'platform', 'last_used_at', 'revoked_at'])]
#[Hidden(['endpoint', 'endpoint_hash', 'p256dh', 'auth'])]
class WebPushSubscription extends Model
{
    /** @use HasFactory<WebPushSubscriptionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            $subscription->uuid ??= (string) Str::uuid();
        });
        static::saving(function (self $subscription): void {
            if ($subscription->isDirty('endpoint')) {
                $subscription->endpoint_hash = hash('sha256', $subscription->endpoint);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'endpoint' => 'encrypted', 'p256dh' => 'encrypted', 'auth' => 'encrypted',
            'last_used_at' => 'datetime', 'revoked_at' => 'datetime',
        ];
    }
}
