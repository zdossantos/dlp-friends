<?php

namespace App\Actions;

use App\Mail\MemberDeletedByAdminMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class DeleteMember
{
    public function __construct(
        private DeactivateDeletedPartner $deactivateDeletedPartner,
        private PurgeDeletedPartnerData $purgeDeletedPartnerData,
    ) {}

    public function handle(User $member): void
    {
        $email = $member->email;
        $locale = $member->preferredLocale();
        $displayName = $member->profile->display_name ?? $email;

        DB::transaction(function () use ($member): void {
            $lockedMember = User::query()->lockForUpdate()->findOrFail($member->id);
            $lockedMember->organizedEvents()
                ->whereNull('cancelled_at')
                ->where('starts_at', '>', now())
                ->update(['cancelled_at' => now()]);
            $this->deactivateDeletedPartner->handle($lockedMember);
            $this->purgeDeletedPartnerData->handle($lockedMember);
            DB::table('sessions')->where('user_id', $lockedMember->id)->delete();
            $lockedMember->delete();
        });

        try {
            Mail::to($email)->queue(
                (new MemberDeletedByAdminMail($displayName))->locale($locale),
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
