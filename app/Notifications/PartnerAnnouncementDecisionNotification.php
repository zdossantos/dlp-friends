<?php

namespace App\Notifications;

use App\Enums\PartnerAnnouncementStatus;
use App\Models\PartnerAnnouncement;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class PartnerAnnouncementDecisionNotification extends Notification
{
    public function __construct(public PartnerAnnouncement $announcement) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        $decision = $this->announcement->status === PartnerAnnouncementStatus::Approved
            ? 'approved'
            : 'rejected';

        return [
            'category' => 'partners',
            'translation_key' => 'notifications.items.partner_announcement_'.$decision,
            'parameters' => ['announcement' => $this->announcement->title],
            'target_type' => 'partner_announcement_management',
            'target_id' => $this->announcement->id,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['id' => $this->id, ...$this->toArray($notifiable)]);
    }
}
