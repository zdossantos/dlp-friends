<?php

namespace Tests\Feature\Console;

use App\Enums\UserDataExportStatus;
use App\Models\User;
use App\Models\UserDataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupExpiredUserDataExportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_deletes_only_expired_files_and_rows(): void
    {
        Storage::fake('exports');
        config()->set('data-control.exports.disk', 'exports');
        $user = User::factory()->create();
        $expired = UserDataExport::factory()->for($user)->create([
            'status' => UserDataExportStatus::Ready,
            'path' => 'expired.json',
            'expires_at' => now()->subSecond(),
        ]);
        $available = UserDataExport::factory()->for($user)->create([
            'status' => UserDataExportStatus::Ready,
            'path' => 'available.json',
            'expires_at' => now()->addHour(),
        ]);
        Storage::disk('exports')->put('expired.json', '{}');
        Storage::disk('exports')->put('available.json', '{}');

        $this->artisan('data-exports:cleanup')->assertSuccessful();

        $this->assertNull($expired->fresh());
        $this->assertNotNull($available->fresh());
        Storage::disk('exports')->assertMissing('expired.json');
        Storage::disk('exports')->assertExists('available.json');
    }
}
