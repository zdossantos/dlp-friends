<?php

namespace App\Actions;

use App\Contracts\PersonalizedWebPushNotification;
use App\Contracts\WebPushNotification;
use App\Contracts\WebPushTransport;
use App\Enums\UserStatus;
use App\Models\WebPushDelivery;
use App\Models\WebPushSubscription;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class DeliverWebPushNotification
{
    public function __construct(private readonly WebPushTransport $transport) {}

    public function handle(WebPushSubscription $subscription, WebPushNotification $notification): void
    {
        $user = $subscription->user()->first();
        $notificationId = $notification instanceof Notification ? $notification->id : null;

        if ($user === null || $notificationId === null || $subscription->revoked_at !== null
            || $user->status !== UserStatus::Active || $user->deletion_requested_at !== null
            || ! $notification->webPushAccessAllowed($user)) {
            return;
        }

        $enabled = $user->notificationPreferences()->where('category', $notification->webPushPreference())->value('enabled') ?? true;
        if (! $enabled) {
            return;
        }

        $delivery = WebPushDelivery::query()->firstOrCreate(
            ['notification_id' => $notificationId, 'web_push_subscription_id' => $subscription->id],
            ['category' => $notification->webPushPreference(), 'status' => 'pending'],
        );
        if (in_array($delivery->status, ['delivered', 'permanent_failure'], true)) {
            return;
        }

        $reserved = WebPushDelivery::query()
            ->whereKey($delivery->id)
            ->where(fn ($query) => $query
                ->whereIn('status', ['pending', 'retrying'])
                ->orWhere(fn ($stale) => $stale
                    ->where('status', 'sending')
                    ->where('updated_at', '<=', now()->subMinutes(10))))
            ->update(['status' => 'sending']);
        if ($reserved !== 1) {
            return;
        }

        $delivery->refresh();
        $delivery->increment('attempts');

        try {
            $locale = $user->preferredLocale();
            $copyKey = 'notifications.push.'.$notification->webPushPreference()->value;
            $copy = $notification instanceof PersonalizedWebPushNotification
                ? $notification->webPushCopy($user, $locale)
                : [
                    'title' => __("{$copyKey}.title", locale: $locale),
                    'body' => __("{$copyKey}.body", locale: $locale),
                ];

            $report = $this->transport->send($subscription, [
                'title' => $copy['title'],
                'body' => $copy['body'],
                'target' => $notification->webPushTarget($user)->url,
                'notification_id' => $notificationId,
                'unread_count' => $user->unreadNotifications()->count(),
                'locale' => $locale,
            ], substr($notificationId, 0, 32));

            $status = $report->statusCode;
            if ($report->successful) {
                $delivery->update(['status' => 'delivered', 'last_status_code' => $status, 'last_error' => null, 'delivered_at' => now()]);
                $subscription->update(['last_used_at' => now()]);

                return;
            }

            if ($report->subscriptionExpired) {
                $subscription->update(['revoked_at' => now()]);
                $delivery->update(['status' => 'permanent_failure', 'last_status_code' => $status, 'last_error' => 'subscription_expired']);

                return;
            }

            $temporary = $status === null || $status === 429 || $status >= 500;
            $delivery->update(['status' => $temporary ? 'retrying' : 'permanent_failure', 'last_status_code' => $status, 'last_error' => 'push_rejected']);
            if ($temporary) {
                throw new RuntimeException('Temporary Web Push rejection.');
            }
        } catch (Throwable $exception) {
            if ($delivery->status === 'sending') {
                $delivery->update(['status' => 'retrying', 'last_error' => 'transport_error']);
            }
            Log::warning('Web Push delivery failed.', [
                'subscription_uuid' => $subscription->uuid,
                'category' => $notification->webPushPreference()->value,
                'notification_id' => $notificationId,
                'status_code' => $delivery->last_status_code,
            ]);
            throw $exception;
        }
    }
}
