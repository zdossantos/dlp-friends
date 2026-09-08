<?php

namespace App\Actions;

use App\Enums\UserDataExportStatus;
use App\Jobs\ExportUserData;
use App\Models\User;
use App\Models\UserDataExport;
use Illuminate\Support\Facades\DB;

final class RequestUserDataExport
{
    public function handle(User $user): UserDataExport
    {
        $created = false;

        $export = DB::transaction(function () use ($user, &$created): UserDataExport {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existing = UserDataExport::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subHours((int) config('data-control.exports.cooldown_hours')))
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $created = true;

            return $user->dataExports()->create([
                'status' => UserDataExportStatus::Pending,
            ]);
        });

        if ($created) {
            ExportUserData::dispatch($export->id);
        }

        return $export;
    }
}
