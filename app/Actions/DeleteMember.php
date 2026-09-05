<?php

namespace App\Actions;

use App\Mail\MemberDeletedByAdminMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class DeleteMember
{
    public function handle(User $member): void
    {
        $email = $member->email;
        $locale = $member->preferredLocale();
        $displayName = $member->profile->display_name ?? $email;

        DB::transaction(function () use ($member): void {
            DB::table('sessions')->where('user_id', $member->id)->delete();
            $member->delete();
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
