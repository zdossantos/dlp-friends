<?php

namespace App\Actions;

use App\Enums\EventRegistrationStatus;
use App\Enums\UserStatus;
use App\Models\ModerationAudit;
use App\Models\User;
use App\Support\MemberPresence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class SetMemberBan
{
    public function __construct(private CancelEvent $cancelEvent, private WithdrawFromEvent $withdraw, private DeactivateDeletedPartner $deactivatePartner, private MemberPresence $presence) {}

    public function handle(User $admin, User $member, bool $banned, string $reason): void
    {
        DB::transaction(function () use ($admin, $member, $banned, $reason): void {
            $users = User::query()->whereKey([$admin->id, $member->id])->orderBy('id')->lockForUpdate()->get();
            $actor = $users->firstWhere('id', $admin->id);
            $target = $users->firstWhere('id', $member->id);
            abort_unless($actor instanceof User && $target instanceof User, 403);
            Gate::forUser($actor)->authorize('ban', $target);
            if (($target->status === UserStatus::Banned) === $banned) {
                return;
            }
            abort_unless($banned || $target->status === UserStatus::Banned, 403);
            $target->forceFill(['status' => $banned ? UserStatus::Banned : UserStatus::Active, 'deletion_requested_at' => null, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $target->id)->delete();
            DB::table('password_reset_tokens')->where('email', $target->email)->delete();
            $target->webPushSubscriptions()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $this->presence->forget($target);
            if ($banned) {
                $target->organizedEvents()->whereNull('cancelled_at')->where('starts_at', '>', now())->orderBy('id')->get()->each(fn ($event) => $this->cancelEvent->handle($target, $event));
                $target->eventRegistrations()->whereIn('status', [EventRegistrationStatus::Pending, EventRegistrationStatus::Accepted])->whereHas('event', fn ($q) => $q->where('starts_at', '>', now()))->with('event')->get()->each(fn ($registration) => $this->withdraw->handle($target, $registration->event));
                $this->deactivatePartner->handle($target);
            }
            if ($banned) {
                DB::afterCommit(fn () => app(RevokeMemberRealtimeAccess::class)->handle($target));
            }
            ModerationAudit::query()->create(['actor_user_id' => $actor->id, 'target_user_id' => $target->id, 'operation' => $banned ? 'ban' : 'unban', 'reason' => $reason]);
        }, 3);
    }
}
