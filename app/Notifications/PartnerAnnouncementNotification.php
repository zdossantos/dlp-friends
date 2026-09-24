<?php

namespace App\Notifications;

use App\Models\PartnerAnnouncement;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use LogicException;

final class PartnerAnnouncementNotification extends Notification
{
    /** @param array<string, mixed>|null $persistedData */
    public function __construct(
        public ?PartnerAnnouncement $announcement,
        private ?array $persistedData = null,
    ) {}

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

        if ($this->announcement === null) {
            throw new LogicException('A new partner notification requires its source announcement.');
        }

        $data = [
            'category' => 'partners',
            'translation_key' => 'notifications.items.partner_announcement',
            'parameters' => ['announcement' => $this->announcement->title],
            'announcement_id' => $this->announcement->id,
            'target_type' => 'partner_announcement',
            'target_id' => $this->announcement->id,
        ];

        $profile = $this->announcement->partnerProfile;

        if ($profile->is_published && $profile->publishedRevision?->image_path !== null) {
            $data['image_url'] = route('partner-profiles.image', $profile, absolute: false);
        }

        return $data;
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['id' => $this->id, ...$this->toArray($notifiable)]);
    }
}
