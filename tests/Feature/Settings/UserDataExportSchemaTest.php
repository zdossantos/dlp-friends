<?php

namespace Tests\Feature\Settings;

use App\Enums\UserDataExportStatus;
use App\Models\User;
use App\Models\UserDataExport;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDataExportSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_lifecycle_is_persisted_for_its_owner(): void
    {
        $user = User::factory()->create();
        $export = UserDataExport::factory()->for($user)->create([
            'status' => UserDataExportStatus::Ready,
            'path' => "user-data-exports/{$user->id}/export.json",
            'expires_at' => now()->addDay(),
        ]);

        $this->assertTrue($export->user->is($user));
        $this->assertSame(UserDataExportStatus::Ready, $export->status);
        $this->assertInstanceOf(CarbonInterface::class, $export->expires_at);
    }

    public function test_export_rows_are_deleted_with_their_owner(): void
    {
        $user = User::factory()->create();
        $export = UserDataExport::factory()->for($user)->create();

        $user->delete();

        $this->assertNull($export->fresh());
    }
}
