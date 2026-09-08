<?php

namespace App\Actions;

use App\Enums\UserDataExportStatus;
use App\Jobs\ExportUserData;
use App\Models\User;
use App\Models\UserDataExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class RequestUserDataExport
{
    public function handle(User $user): UserDataExport
    {
        $shouldDispatch = false;

        $export = DB::transaction(function () use ($user, &$shouldDispatch): UserDataExport {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existing = UserDataExport::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subHours((int) config('data-control.exports.cooldown_hours')))
                ->latest('id')
                ->first();

            if ($existing !== null) {
                $fileIsMissing = $existing->status === UserDataExportStatus::Ready
                    && ($existing->path === null
                        || ! Storage::disk((string) config('data-control.exports.disk'))->exists($existing->path));

                if ($existing->status === UserDataExportStatus::Failed || $fileIsMissing) {
                    $existing->update([
                        'status' => UserDataExportStatus::Pending,
                        'path' => null,
                        'failure_reason' => null,
                        'expires_at' => null,
                    ]);
                    $shouldDispatch = true;
                }

                return $existing;
            }

            $shouldDispatch = true;

            return $user->dataExports()->create([
                'status' => UserDataExportStatus::Pending,
            ]);
        });

        if ($shouldDispatch) {
            ExportUserData::dispatch($export->id);
        }

        return $export;
    }
}
