<?php

namespace App\Notifications;

use App\Contracts\WebPushNotification;
use App\Enums\RoleName;
use App\Enums\WebPushPreference;
use App\Models\ApplicationAnnouncement;
use App\Models\ApplicationAnnouncementDelivery;
use App\Models\User;
use App\Support\PublicUrls;
use App\Support\WebPushTarget;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class ApplicationAnnouncementNotification extends Notification implements WebPushNotification
{
    /** @param array<string, mixed>|null $persistedData */
    public function __construct(public ApplicationAnnouncement $announcement, private ?array $persistedData = null) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        if ($this->persistedData !== null) {
            return $this->persistedData;
        }
        $locale = $notifiable->preferredLocale() === 'en' ? 'en' : 'fr';

        return [
            'category' => 'application', 'translation_key' => 'notifications.items.application_announcement',
            'parameters' => ['announcement' => $this->announcement->getAttribute('title_'.$locale)],
            'content' => $this->announcement->getAttribute('content_'.$locale),
            'link_url' => $this->announcement->is_release ? PublicUrls::releaseNotesPath($locale) : $this->announcement->destination_url,
            'target_type' => 'application_announcement', 'target_id' => $this->announcement->id,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['id' => $this->id, ...$this->toArray($notifiable)]);
    }

    public function webPushPreference(): WebPushPreference
    {
        return WebPushPreference::ApplicationAnnouncements;
    }

    public function webPushTarget(User $notifiable): WebPushTarget
    {
        return new WebPushTarget(match (true) {
            $notifiable->hasRole(RoleName::Admin) => '/admin/notifications',
            $notifiable->hasRole(RoleName::Partner) => '/partner/notifications',
            default => '/notifications',
        });
    }

    public function webPushAccessAllowed(User $notifiable): bool
    {
        return $this->announcement->send_push
            && User::query()->eligibleForApplicationAnnouncements()->whereKey($notifiable->id)->exists()
            && ApplicationAnnouncementDelivery::query()->where('user_id', $notifiable->id)
                ->where('notification_id', $this->id)->where('status', 'delivered')->whereHas('notification')->exists();
    }
}
