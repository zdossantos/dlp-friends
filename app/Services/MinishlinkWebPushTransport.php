<?php

namespace App\Services;

use App\Contracts\WebPushTransport;
use App\Models\WebPushSubscription;
use App\Support\WebPushResult;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

final class MinishlinkWebPushTransport implements WebPushTransport
{
    public function __construct(private readonly WebPush $webPush) {}

    public static function fromConfig(): self
    {
        $subject = config('services.web_push.subject');
        $publicKey = config('services.web_push.public_key');
        $privateKey = config('services.web_push.private_key');
        if (! is_string($subject) || ! is_string($publicKey) || ! is_string($privateKey)
            || $subject === '' || $publicKey === '' || $privateKey === '') {
            throw new RuntimeException('Web Push VAPID configuration is incomplete.');
        }

        return new self(new WebPush(['VAPID' => [
            'subject' => $subject,
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        ]]));
    }

    public function send(WebPushSubscription $subscription, array $payload, string $topic): WebPushResult
    {
        $report = $this->webPush->sendOneNotification(new Subscription(
            $subscription->endpoint,
            $subscription->p256dh,
            $subscription->auth,
            $subscription->content_encoding,
        ), json_encode($payload, JSON_THROW_ON_ERROR), [
            'TTL' => 300,
            'urgency' => 'normal',
            'topic' => $topic,
        ]);

        return new WebPushResult(
            $report->isSuccess(),
            $report->isSubscriptionExpired(),
            $report->getResponse()?->getStatusCode(),
        );
    }
}
