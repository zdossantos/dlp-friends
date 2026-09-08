<?php

namespace App\Jobs;

use App\Actions\BuildUserDataExport;
use App\Enums\UserDataExportStatus;
use App\Models\UserDataExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ExportUserData implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $exportId) {}

    public function handle(?BuildUserDataExport $builder = null): void
    {
        $export = DB::transaction(function (): ?UserDataExport {
            $export = UserDataExport::query()->lockForUpdate()->find($this->exportId);

            if ($export === null || ! in_array($export->status, [UserDataExportStatus::Pending, UserDataExportStatus::Failed], true)) {
                return null;
            }

            if (! $export->user()->exists()) {
                return null;
            }

            $export->update([
                'status' => UserDataExportStatus::Processing,
                'failure_reason' => null,
                'expires_at' => null,
            ]);

            return $export;
        });

        if ($export === null) {
            return;
        }

        $disk = Storage::disk((string) config('data-control.exports.disk'));
        $path = "user-data-exports/{$export->user_id}/{$export->id}.json";

        try {
            if ($export->path !== null) {
                $disk->delete($export->path);
            }

            $payload = ($builder ?? app(BuildUserDataExport::class))->handle($export->user);
            $json = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )."\n";

            $disk->put($path, $json);
            $export->update([
                'status' => UserDataExportStatus::Ready,
                'path' => $path,
                'expires_at' => now()->addHours((int) config('data-control.exports.expires_hours')),
            ]);
        } catch (Throwable $exception) {
            $disk->delete($path);
            $export->update([
                'status' => UserDataExportStatus::Failed,
                'path' => null,
                'failure_reason' => $exception::class,
                'expires_at' => null,
            ]);

            throw $exception;
        }
    }
}
