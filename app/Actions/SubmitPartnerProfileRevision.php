<?php

namespace App\Actions;

use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitPartnerProfileRevision
{
    public function handle(User $partner): PartnerProfileRevision
    {
        return DB::transaction(function () use ($partner): PartnerProfileRevision {
            User::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();
            $profile = PartnerProfile::query()
                ->where('user_id', $partner->id)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                throw ValidationException::withMessages([
                    'profile' => __('partners.errors.draft_missing'),
                ]);
            }

            $draft = $profile->revisions()
                ->where('status', PartnerRevisionStatus::Draft)
                ->lockForUpdate()
                ->first();

            if ($draft === null) {
                throw ValidationException::withMessages([
                    'profile' => __('partners.errors.draft_missing'),
                ]);
            }

            if ($draft->image_path === null) {
                throw ValidationException::withMessages([
                    'image' => __('partners.errors.image_required'),
                ]);
            }

            $draft->update([
                'status' => PartnerRevisionStatus::PendingApproval,
                'submitted_at' => now(),
                'draft_key' => null,
            ]);

            return $draft->refresh();
        });
    }
}
