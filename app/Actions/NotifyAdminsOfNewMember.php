<?php

namespace App\Actions;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Events\NewMemberNotificationBroadcast;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\NewMemberNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NotifyAdminsOfNewMember
{
    public function handle(User $member): void
    {
        DB::transaction(function () use ($member): void {
            $member = User::query()->lockForUpdate()->findOrFail($member->id);

            if ($member->self_registered_at === null || $member->new_member_announced_at !== null
                || $member->status !== UserStatus::Active || $member->deletion_requested_at !== null
                || ! $member->hasVerifiedEmail() || ! $member->hasRole(RoleName::User)
                || ! ($member->profile?->isComplete() ?? false)) {
                return;
            }

            $admins = User::query()
                ->where('status', UserStatus::Active)
                ->whereNull('deletion_requested_at')
                ->where('admin_new_member_alerts', true)
                ->whereHas('roles', fn ($query) => $query->where('name', RoleName::Admin))
                ->orderBy('id')->lockForUpdate()->get();

            foreach ($admins as $admin) {
                $notification = new NewMemberNotification($member->id);
                $notification->id = (string) Str::uuid();
                $admin->notifyNow($notification, ['database']);

                DB::afterCommit(function () use ($admin, $notification): void {
                    $recipient = User::query()->find($admin->id);
                    if ($recipient !== null && $notification->webPushAccessAllowed($recipient)) {
                        rescue(fn () => event(new NewMemberNotificationBroadcast($recipient, $notification)));
                        rescue(fn () => $recipient->notifyNow($notification, [WebPushChannel::class]));
                    }
                });
            }

            $member->forceFill(['new_member_announced_at' => now()])->save();
        }, 3);
    }
}
