<?php

namespace App\Console\Commands;

use App\Models\UserDataExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class CleanupExpiredUserDataExports extends Command
{
    protected $signature = 'data-exports:cleanup';

    protected $description = 'Delete expired personal data export files and records';

    public function handle(): int
    {
        $disk = Storage::disk((string) config('data-control.exports.disk'));

        UserDataExport::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($exports) use ($disk): void {
                foreach ($exports as $export) {
                    if ($export->path !== null) {
                        $disk->delete($export->path);
                    }

                    $export->delete();
                }
            });

        return self::SUCCESS;
    }
}
