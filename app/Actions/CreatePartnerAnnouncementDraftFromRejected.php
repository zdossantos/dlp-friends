<?php

namespace App\Actions;

use App\Enums\PartnerAnnouncementStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Rules\SafeHttpsUrl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CreatePartnerAnnouncementDraftFromRejected
{
    public function handle(User $partner, PartnerAnnouncement $announcement): PartnerAnnouncement
    {
        return DB::transaction(function () use ($partner, $announcement): PartnerAnnouncement {
            $profile = PartnerProfile::query()
                ->whereKey($announcement->partner_profile_id)
                ->lockForUpdate()
                ->firstOrFail();
            $rejected = PartnerAnnouncement::query()
                ->whereKey($announcement->id)
                ->where('partner_profile_id', $profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($profile->user_id !== $partner->id) {
                throw new AuthorizationException;
            }

            if ($rejected->status !== PartnerAnnouncementStatus::Rejected) {
                throw ValidationException::withMessages([
                    'announcement' => __('partners.announcements.errors.not_rejected'),
                ]);
            }

            $data = $this->validatedContent($rejected);
            $existingDraft = $profile->announcements()
                ->where('status', PartnerAnnouncementStatus::Draft)
                ->where($data)
                ->lockForUpdate()
                ->first();

            if ($existingDraft !== null) {
                return $existingDraft;
            }

            return $profile->announcements()->create([
                ...$data,
                'status' => PartnerAnnouncementStatus::Draft,
            ]);
        });
    }

    /** @return array{title: string, content: string, destination_url: string} */
    private function validatedContent(PartnerAnnouncement $announcement): array
    {
        /** @var array{title: string, content: string, destination_url: string} $validated */
        $validated = Validator::make($announcement->only([
            'title',
            'content',
            'destination_url',
        ]), [
            'title' => ['required', 'string', 'max:80'],
            'content' => ['required', 'string', 'max:500'],
            'destination_url' => ['required', 'string', 'max:2048', new SafeHttpsUrl],
        ])->validate();

        return $validated;
    }
}
