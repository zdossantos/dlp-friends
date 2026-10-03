<?php

namespace App\Notifications;

use App\Contracts\PersonalizedWebPushNotification;
use App\Contracts\WebPushNotification;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\WebPushPreference;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Support\MemberNotificationPresenter;
use App\Support\WebPushTarget;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class NewMemberNotification extends Notification implements PersonalizedWebPushNotification, WebPushNotification
{
    public function __construct(public int $memberId) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'administration',
            'translation_key' => 'notifications.items.new_member',
            'parameters' => [],
            'target_type' => 'admin_member',
            'target_id' => $this->memberId,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['id' => $this->id, ...$this->toArray($notifiable)]);
    }

    public function webPushPreference(): WebPushPreference
    {
        return WebPushPreference::Administration;
    }

    public function webPushTarget(User $notifiable): WebPushTarget
    {
        return new WebPushTarget(app(MemberNotificationPresenter::class)->targetUrl($this->toArray($notifiable), $notifiable));
    }

    public function webPushAccessAllowed(User $notifiable): bool
    {
        return $notifiable->status === UserStatus::Active
            && $notifiable->deletion_requested_at === null
            && $notifiable->hasRole(RoleName::Admin)
            && $notifiable->admin_new_member_alerts;
    }

    /** @return array{title: string, body: string} */
    public function webPushCopy(User $notifiable, string $locale): array
    {
        return [
            'title' => __('notifications.push.new_member.title', locale: $locale),
            'body' => __('notifications.push.new_member.body', locale: $locale),
        ];
    }
}
