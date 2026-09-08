<?php

namespace Tests\Feature\Jobs;

use App\Enums\UserDataExportStatus;
use App\Jobs\ExportUserData;
use App\Models\Interest;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use App\Models\UserDataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportUserDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_exports_only_the_members_portable_data(): void
    {
        Storage::fake('exports');
        config()->set('data-control.exports.disk', 'exports');

        $user = User::factory()->withProfile()->create([
            'email' => 'self@example.com',
            'locale' => 'fr',
        ]);
        $other = User::factory()->withProfile()->create([
            'email' => 'other-private@example.com',
            'birth_date' => '1985-02-03',
        ]);
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret' => 'two-factor-sentinel',
            'remember_token' => 'remember-token-sentinel',
        ]);

        $activeInterest = Interest::factory()->create([
            'name' => 'Attractions',
            'name_en' => 'Rides',
            'is_active' => true,
        ]);
        $archivedInterest = Interest::factory()->create([
            'name' => 'Archives',
            'name_en' => null,
            'is_active' => false,
        ]);
        $user->profile->interestHistory()->attach([
            $activeInterest->id => ['is_selected' => true],
            $archivedInterest->id => ['is_selected' => false],
        ]);

        [$lowId, $highId] = $user->id < $other->id
            ? [$user->id, $other->id]
            : [$other->id, $user->id];
        $match = MemberMatch::factory()->create([
            'user_low_id' => $lowId,
            'user_high_id' => $highId,
        ]);
        $conversation = $match->conversation()->create();
        Message::factory()->for($conversation)->create([
            'author_user_id' => $user->id,
            'content' => 'Mon message exporté',
        ]);
        Message::factory()->for($conversation)->create([
            'author_user_id' => $other->id,
            'content' => 'Sa réponse exportée',
        ]);

        $export = UserDataExport::factory()->for($user)->create();

        (new ExportUserData($export->id))->handle();

        $export->refresh();
        Storage::disk('exports')->assertExists($export->path);
        $payload = json_decode(
            Storage::disk('exports')->get($export->path),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame(
            ['format_version', 'generated_at', 'account', 'profile', 'interests', 'matches', 'messages'],
            array_keys($payload),
        );
        $this->assertSame('self@example.com', $payload['account']['email']);
        $this->assertSame(['Attractions', 'Archives'], array_column($payload['interests'], 'name_fr'));
        $this->assertSame($other->profile->display_name, $payload['matches'][0]['other_member']['display_name']);
        $this->assertSame(['self', 'other'], array_column($payload['messages'], 'author'));
        $this->assertSame(['Mon message exporté', 'Sa réponse exportée'], array_column($payload['messages'], 'content'));
        $this->assertSame(UserDataExportStatus::Ready, $export->status);
        $this->assertNotNull($export->expires_at);

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('two-factor-sentinel', $json);
        $this->assertStringNotContainsString('remember-token-sentinel', $json);
        $this->assertStringNotContainsString('other-private@example.com', $json);
        $this->assertStringNotContainsString('1985-02-03', $json);
    }

    public function test_job_stops_when_the_owner_no_longer_exists(): void
    {
        Storage::fake('exports');
        $user = User::factory()->create();
        $export = UserDataExport::factory()->for($user)->create();
        $exportId = $export->id;
        $user->delete();

        (new ExportUserData($exportId))->handle();

        Storage::disk('exports')->assertDirectoryEmpty('/');
    }
}
