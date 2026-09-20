<?php

namespace App\Notifications;

use App\Models\PartnerAnnouncement;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class PartnerAnnouncementNotification extends Notification
{
    public function __construct(public PartnerAnnouncement $announcement) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array{category: string, translation_key: string, parameters: array{announcement: string}, announcement_id: int, target_type: string, target_id: int} */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => 'partners',
            'translation_key' => 'notifications.items.partner_announcement',
            'parameters' => ['announcement' => $this->announcement->title],
            'announcement_id' => $this->announcement->id,
            'target_type' => 'partner_announcement',
            'target_id' => $this->announcement->id,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['id' => $this->id, ...$this->toArray($notifiable)]);
    }
}
