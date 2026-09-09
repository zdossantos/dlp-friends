<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserDataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_download_personal_data_directly_without_persisting_an_export(): void
    {
        $this->travelTo(Carbon::parse('2026-09-08 12:00:00'), fn () => $this->actingAs(User::factory()->withProfile()->create())
            ->post(route('data-export.store'))
            ->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8')
            ->assertDownload('dlp-friends-data-2026-09-08.json'));
    }

    public function test_guest_cannot_download_personal_data(): void
    {
        $this->post(route('data-export.store'))->assertRedirect(route('login'));
    }
}
