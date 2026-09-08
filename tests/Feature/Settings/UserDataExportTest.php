<?php

namespace Tests\Feature\Settings;

use App\Enums\UserDataExportStatus;
use App\Jobs\ExportUserData;
use App\Models\User;
use App\Models\UserDataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserDataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_settings_exposes_the_latest_export_state_and_temporary_url(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $user = User::factory()->withProfile()->create();
        UserDataExport::factory()->for($user)->create([
            'status' => UserDataExportStatus::Ready,
            'path' => 'ready.json',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($user)->get(route('account.edit'))->assertInertia(fn (Assert $page) => $page
            ->where('dataExport.status', 'ready')
            ->where('dataExport.expires_at', fn ($value) => is_string($value))
            ->where('dataExport.download_url', fn ($value) => is_string($value) && str_contains($value, '/settings/data-export/')));
    }

    public function test_member_can_request_one_asynchronous_export_during_the_cooldown(): void
    {
        Queue::fake();
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)
            ->post(route('data-export.store'))
            ->assertRedirect(route('account.edit'));
        $this->actingAs($user)
            ->post(route('data-export.store'))
            ->assertRedirect(route('account.edit'));

        $this->assertSame(1, $user->dataExports()->count());
        Queue::assertPushed(ExportUserData::class, 1);
    }

    public function test_guest_cannot_request_or_download_an_export(): void
    {
        $user = User::factory()->create();
        $export = UserDataExport::factory()->for($user)->create();

        $this->post(route('data-export.store'))->assertRedirect(route('login'));
        $this->get(URL::temporarySignedRoute(
            'data-export.download',
            now()->addMinutes(10),
            ['export' => $export],
        ))->assertRedirect(route('login'));
    }

    public function test_only_owner_can_download_a_ready_export_through_a_valid_signature(): void
    {
        Storage::fake('exports');
        config()->set('data-control.exports.disk', 'exports');
        $user = User::factory()->withProfile()->create();
        $other = User::factory()->withProfile()->create();
        $path = "user-data-exports/{$user->id}/ready.json";
        Storage::disk('exports')->put($path, '{"account":{}}');
        $export = UserDataExport::factory()->for($user)->create([
            'status' => UserDataExportStatus::Ready,
            'path' => $path,
            'expires_at' => now()->addHour(),
        ]);
        $url = URL::temporarySignedRoute(
            'data-export.download',
            now()->addMinutes(10),
            ['export' => $export],
        );

        $this->actingAs($other)->get($url)->assertNotFound();
        $this->actingAs($user)->get(route('data-export.download', $export))->assertForbidden();
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertDownload("dlp-friends-data-{$export->id}.json");
    }

    public function test_expired_or_missing_export_is_not_downloadable(): void
    {
        Storage::fake('exports');
        $user = User::factory()->withProfile()->create();
        $export = UserDataExport::factory()->for($user)->create([
            'status' => UserDataExportStatus::Ready,
            'path' => 'missing.json',
            'expires_at' => now()->subSecond(),
        ]);
        $url = URL::temporarySignedRoute(
            'data-export.download',
            now()->addMinutes(10),
            ['export' => $export],
        );

        $this->actingAs($user)->get($url)->assertNotFound();
    }
}
