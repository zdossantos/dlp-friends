<?php

namespace App\Actions;

use App\Contracts\WebPushNotification;
use App\Enums\UserStatus;
use App\Models\WebPushDelivery;
use App\Models\WebPushSubscription;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;
use Throwable;

final class DeliverWebPushNotification
{
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

        $delivery->increment('attempts');

        try {
            $report = $this->webPush()->sendOneNotification(new Subscription(
                $subscription->endpoint,
                $subscription->p256dh,
                $subscription->auth,
                $subscription->content_encoding,
            ), json_encode([
                'title' => __('notifications.push.title', locale: $user->preferredLocale()),
                'body' => __('notifications.push.'.$notification->webPushPreference()->value, locale: $user->preferredLocale()),
                'target' => $notification->webPushTarget($user)->url,
                'notification_id' => $notificationId,
                'unread_count' => $user->unreadNotifications()->count(),
            ], JSON_THROW_ON_ERROR), ['TTL' => 300, 'urgency' => 'normal', 'topic' => substr($notificationId, 0, 32)]);

            $status = $report->getResponse()?->getStatusCode();
            if ($report->isSuccess()) {
                $delivery->update(['status' => 'delivered', 'last_status_code' => $status, 'last_error' => null, 'delivered_at' => now()]);
                $subscription->update(['last_used_at' => now()]);

                return;
            }

            if ($report->isSubscriptionExpired()) {
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
            if ($delivery->status === 'pending') {
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

    private function webPush(): WebPush
    {
        $subject = config('services.web_push.subject');
        $publicKey = config('services.web_push.public_key');
        $privateKey = config('services.web_push.private_key');
        if (! is_string($subject) || ! is_string($publicKey) || ! is_string($privateKey)
            || $subject === '' || $publicKey === '' || $privateKey === '') {
            throw new RuntimeException('Web Push VAPID configuration is incomplete.');
        }

        return new WebPush(['VAPID' => ['subject' => $subject, 'publicKey' => $publicKey, 'privateKey' => $privateKey]]);
    }
}
