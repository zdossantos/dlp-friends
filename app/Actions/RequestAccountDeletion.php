<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class RequestAccountDeletion
{
    public function __construct(private CancelEvent $cancelEvent) {}

    public function handle(User $user): CarbonImmutable
    {
        return DB::transaction(function () use ($user): CarbonImmutable {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->status === UserStatus::PendingDeletion && $lockedUser->deletion_requested_at !== null) {
                return $lockedUser->deletion_requested_at;
            }

            $lockedUser->organizedEvents()
                ->whereNull('cancelled_at')
                ->where('starts_at', '>', now())
                ->orderBy('id')
                ->get()
                ->each(fn ($event) => $this->cancelEvent->handle($lockedUser, $event));

            $requestedAt = CarbonImmutable::now();
            $lockedUser->forceFill([
                'status' => UserStatus::PendingDeletion,
                'deletion_requested_at' => $requestedAt,
            ])->save();

            DB::table('sessions')->where('user_id', $lockedUser->id)->delete();
            $lockedUser->socialAccounts()->delete();

            DB::afterCommit(function () use ($lockedUser, $requestedAt): void {
                PurgeDeletedUser::dispatch($lockedUser->id, $requestedAt->toISOString())
                    ->delay($requestedAt->addDays((int) config('data-control.deletion.purge_days', 30)));
            });

            return $requestedAt;
        });
    }
}
