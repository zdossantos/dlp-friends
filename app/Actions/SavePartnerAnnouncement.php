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

final class SavePartnerAnnouncement
{
    /**
     * @param  array{title: string, content: string, destination_url: string}  $data
     */
    public function handle(
        User $partner,
        array $data,
        ?PartnerAnnouncement $announcement = null,
    ): PartnerAnnouncement {
        $validated = $this->validate($data);

        return DB::transaction(function () use ($partner, $validated, $announcement): PartnerAnnouncement {
            $profile = PartnerProfile::query()
                ->where('user_id', $partner->id)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                throw ValidationException::withMessages([
                    'profile' => __('partners.announcements.errors.profile_required'),
                ]);
            }

            if ($announcement === null) {
                return $profile->announcements()->create([
                    ...$validated,
                    'status' => PartnerAnnouncementStatus::Draft,
                ]);
            }

            $locked = PartnerAnnouncement::query()
                ->whereKey($announcement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->partner_profile_id !== $profile->id) {
                throw new AuthorizationException;
            }

            if ($locked->status !== PartnerAnnouncementStatus::Draft) {
                throw ValidationException::withMessages([
                    'announcement' => __('partners.announcements.errors.not_draft'),
                ]);
            }

            $locked->update($validated);

            return $locked->refresh();
        });
    }

    /**
     * @param  array{title: string, content: string, destination_url: string}  $data
     * @return array{title: string, content: string, destination_url: string}
     */
    private function validate(array $data): array
    {
        /** @var array{title: string, content: string, destination_url: string} $validated */
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:80'],
            'content' => ['required', 'string', 'max:500'],
            'destination_url' => ['required', 'string', 'max:2048', new SafeHttpsUrl],
        ])->validate();

        return $validated;
    }
}
