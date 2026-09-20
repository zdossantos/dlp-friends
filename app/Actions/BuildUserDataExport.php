<?php

namespace App\Actions;

use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventChatMessage;
use App\Models\EventRegistration;
use App\Models\Interest;
use App\Models\MemberMatch;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\RoleAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class BuildUserDataExport
{
    /** @return array<string, mixed> */
    public function handle(User $user): array
    {
        $user->loadMissing('profile.avatar', 'profile.interestHistory');

        $partnerPreference = $user->partnerNotificationPreference()->first();
        $partnerProfile = PartnerProfile::query()
            ->where('user_id', $user->id)
            ->first();
        $partnerProfileId = $partnerProfile?->id;

        $matches = MemberMatch::query()
            ->where(fn (Builder $query) => $query
                ->where('user_low_id', $user->id)
                ->orWhere('user_high_id', $user->id))
            ->with('lowUser.profile', 'highUser.profile')
            ->orderBy('id')
            ->get();

        $conversations = Conversation::query()
            ->forMember($user)
            ->withVisibleParticipant($user)
            ->with(['messages' => fn ($query) => $query
                ->where('author_user_id', $user->id)
                ->orderBy('id')])
            ->orderBy('id')
            ->get();

        $messages = [];
        foreach ($conversations as $conversation) {
            foreach ($conversation->messages as $message) {
                $messages[] = [
                    'conversation_id' => $conversation->id,
                    'author' => $message->author_user_id === $user->id ? 'self' : 'other',
                    'content' => $message->content,
                    'read_at' => $message->read_at?->toIso8601String(),
                    'created_at' => $message->created_at?->toIso8601String(),
                    'updated_at' => $message->updated_at?->toIso8601String(),
                ];
            }
        }

        return [
            'format_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'account' => [
                'email' => $user->email,
                'locale' => $user->locale,
                'birth_date' => $user->birth_date?->toDateString(),
                'status' => $user->status->value,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'profile' => $user->profile === null ? null : [
                'display_name' => $user->profile->display_name,
                'bio' => $user->profile->bio,
                'visit_frequency' => $user->profile->visit_frequency?->value,
                'visibility' => $user->profile->visibility->value,
                'avatar' => $user->profile->avatar === null ? null : [
                    'id' => $user->profile->avatar->id,
                    'name' => $user->profile->avatar->name,
                ],
                'created_at' => $user->profile->created_at?->toIso8601String(),
                'updated_at' => $user->profile->updated_at?->toIso8601String(),
            ],
            'interests' => $user->profile?->interestHistory
                ->sortBy('id')
                ->values()
                ->map(fn (Interest $interest): array => [
                    'id' => $interest->id,
                    'name_fr' => $interest->name,
                    'name_en' => $interest->name_en,
                    'is_active' => $interest->is_active,
                    'is_selected' => (bool) data_get($interest, 'pivot.is_selected'),
                ])->all() ?? [],
            'matches' => $matches->map(function (MemberMatch $match) use ($user): array {
                $other = $match->user_low_id === $user->id ? $match->highUser : $match->lowUser;

                return [
                    'id' => $match->id,
                    'other_member' => [
                        'id' => $other->id,
                        'display_name' => $other->profile?->display_name,
                    ],
                    'created_at' => $match->created_at?->toIso8601String(),
                    'updated_at' => $match->updated_at?->toIso8601String(),
                ];
            })->all(),
            'messages' => $messages,
            'event_chat_messages' => EventChatMessage::query()
                ->where('author_user_id', $user->id)
                ->orderBy('id')
                ->get()
                ->map(fn (EventChatMessage $message): array => [
                    'event_chat_id' => $message->event_chat_id,
                    'content' => $message->content,
                    'created_at' => $message->created_at?->toIso8601String(),
                    'updated_at' => $message->updated_at?->toIso8601String(),
                ])->all(),
            'organized_events' => Event::query()
                ->where('organizer_user_id', $user->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Event $event): array => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'general_location' => $event->general_location,
                    'detailed_location' => $event->detailed_location,
                    'starts_at' => $event->starts_at->toIso8601String(),
                    'capacity' => $event->capacity,
                    'registration_mode' => $event->registration_mode->value,
                    'cancelled_at' => $event->cancelled_at?->toIso8601String(),
                ])->all(),
            'event_registrations' => EventRegistration::query()
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->get()
                ->map(fn (EventRegistration $registration): array => [
                    'event_id' => $registration->event_id,
                    'status' => $registration->status->value,
                    'created_at' => $registration->created_at?->toIso8601String(),
                    'updated_at' => $registration->updated_at?->toIso8601String(),
                ])->all(),
            'notifications' => $user->notifications()
                ->orderBy('created_at')
                ->get()
                ->map(function ($notification): array {
                    $data = $notification->data;

                    return [
                        'category' => $data['category'] ?? null,
                        'translation_key' => $data['translation_key'] ?? null,
                        'parameters' => $data['parameters'] ?? [],
                        'target_type' => $data['target_type'] ?? null,
                        'target_id' => $data['target_id'] ?? null,
                        'read_at' => $notification->read_at?->toIso8601String(),
                        'created_at' => $notification->created_at?->toIso8601String(),
                    ];
                })->all(),
            'notification_preferences' => [
                'partner_announcements' => $partnerPreference->enabled ?? false,
                'updated_at' => $partnerPreference?->updated_at?->toIso8601String(),
            ],
            'role_history' => RoleAudit::query()
                ->where('target_user_id', $user->id)
                ->orderBy('id')
                ->get()
                ->map(fn (RoleAudit $audit): array => [
                    'id' => $audit->id,
                    'role' => $audit->role->value,
                    'action' => $audit->action->value,
                    'actor' => $audit->actor_user_id === null ? 'system' : 'administrator',
                    'expires_at' => $audit->expires_at?->toIso8601String(),
                    'created_at' => $audit->created_at?->toIso8601String(),
                    'updated_at' => $audit->updated_at?->toIso8601String(),
                ])->all(),
            'partner_profile' => $partnerProfile === null ? null : [
                'id' => $partnerProfile->id,
                'published_revision_id' => $partnerProfile->published_revision_id,
                'is_published' => $partnerProfile->is_published,
                'position' => $partnerProfile->position,
                'created_at' => $partnerProfile->created_at?->toIso8601String(),
                'updated_at' => $partnerProfile->updated_at?->toIso8601String(),
            ],
            'partner_profile_revisions' => $partnerProfileId === null ? [] : PartnerProfileRevision::query()
                ->where('partner_profile_id', $partnerProfileId)
                ->orderBy('id')
                ->get()
                ->map(fn (PartnerProfileRevision $revision): array => [
                    'id' => $revision->id,
                    'partner_profile_id' => $revision->partner_profile_id,
                    'name_fr' => $revision->name_fr,
                    'name_en' => $revision->name_en,
                    'description_fr' => $revision->description_fr,
                    'description_en' => $revision->description_en,
                    'has_image' => $revision->image_path !== null,
                    'status' => $revision->status->value,
                    'submitted_at' => $revision->submitted_at?->toIso8601String(),
                    'decided_at' => $revision->decided_at?->toIso8601String(),
                    'rejection_reason' => $revision->rejection_reason,
                    'draft_key' => $revision->draft_key,
                    'expires_at' => $revision->expires_at?->toIso8601String(),
                    'created_at' => $revision->created_at?->toIso8601String(),
                    'updated_at' => $revision->updated_at?->toIso8601String(),
                ])->all(),
            'partner_announcements' => $partnerProfileId === null ? [] : PartnerAnnouncement::query()
                ->where('partner_profile_id', $partnerProfileId)
                ->with('metric')
                ->orderBy('id')
                ->get()
                ->map(fn (PartnerAnnouncement $announcement): array => [
                    'id' => $announcement->id,
                    'partner_profile_id' => $announcement->partner_profile_id,
                    'title' => $announcement->title,
                    'content' => $announcement->content,
                    'destination_url' => $announcement->destination_url,
                    'status' => $announcement->status->value,
                    'submitted_at' => $announcement->submitted_at?->toIso8601String(),
                    'audience_prepared_at' => $announcement->audience_prepared_at?->toIso8601String(),
                    'sending_started_at' => $announcement->sending_started_at?->toIso8601String(),
                    'sent_at' => $announcement->sent_at?->toIso8601String(),
                    'decided_at' => $announcement->decided_at?->toIso8601String(),
                    'rejection_reason' => $announcement->rejection_reason,
                    'expires_at' => $announcement->expires_at?->toIso8601String(),
                    'created_at' => $announcement->created_at?->toIso8601String(),
                    'updated_at' => $announcement->updated_at?->toIso8601String(),
                    'metrics' => $announcement->metric === null ? null : [
                        'id' => $announcement->metric->id,
                        'prepared_count' => $announcement->metric->prepared_count,
                        'delivered_count' => $announcement->metric->delivered_count,
                        'read_count' => $announcement->metric->read_count,
                        'dismissed_count' => $announcement->metric->dismissed_count,
                        'unique_click_count' => $announcement->metric->unique_click_count,
                        'total_click_count' => $announcement->metric->total_click_count,
                        'expires_at' => $announcement->metric->expires_at?->toIso8601String(),
                        'created_at' => $announcement->metric->created_at?->toIso8601String(),
                        'updated_at' => $announcement->metric->updated_at?->toIso8601String(),
                    ],
                ])->all(),
            'received_partner_announcements' => PartnerAnnouncementDelivery::query()
                ->where('user_id', $user->id)
                ->with('announcement:id,title,content,destination_url')
                ->orderBy('id')
                ->get()
                ->map(fn (PartnerAnnouncementDelivery $delivery): array => [
                    'id' => $delivery->id,
                    'announcement_id' => $delivery->partner_announcement_id,
                    'title' => $delivery->announcement->title,
                    'content' => $delivery->announcement->content,
                    'destination_url' => $delivery->announcement->destination_url,
                    'status' => $delivery->status->value,
                    'delivered_at' => $delivery->delivered_at?->toIso8601String(),
                    'read_at' => $delivery->read_at?->toIso8601String(),
                    'dismissed_at' => $delivery->dismissed_at?->toIso8601String(),
                    'first_clicked_at' => $delivery->first_clicked_at?->toIso8601String(),
                    'click_count' => $delivery->click_count,
                    'created_at' => $delivery->created_at?->toIso8601String(),
                    'updated_at' => $delivery->updated_at?->toIso8601String(),
                ])->all(),
        ];
    }
}
