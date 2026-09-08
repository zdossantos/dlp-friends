<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Jobs\PurgeDeletedUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class RequestAccountDeletion
{
    public function handle(User $user): CarbonImmutable
    {
        return DB::transaction(function () use ($user): CarbonImmutable {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->status === UserStatus::PendingDeletion && $lockedUser->deletion_requested_at !== null) {
                return $lockedUser->deletion_requested_at;
            }

            $requestedAt = CarbonImmutable::now();
            $lockedUser->forceFill([
                'status' => UserStatus::PendingDeletion,
                'deletion_requested_at' => $requestedAt,
            ])->save();

            DB::table('sessions')->where('user_id', $lockedUser->id)->delete();
            $lockedUser->socialAccounts()->delete();

            $disk = Storage::disk((string) config('data-control.exports.disk', 'exports'));
            foreach ($lockedUser->dataExports as $export) {
                if ($export->path !== null) {
                    $disk->delete($export->path);
                }
            }
            $lockedUser->dataExports()->delete();

            DB::afterCommit(function () use ($lockedUser, $requestedAt): void {
                PurgeDeletedUser::dispatch($lockedUser->id, $requestedAt->toISOString())
                    ->delay($requestedAt->addDays((int) config('data-control.deletion.purge_days', 30)));
            });

            return $requestedAt;
        });
    }
}
