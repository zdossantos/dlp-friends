<?php

use App\Actions\PrepareApplicationAnnouncement;
use App\Actions\SendApplicationAnnouncement;
use App\Enums\WebPushPreference;
use App\Jobs\DeliverApplicationAnnouncement;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

test('delivery sees an eligibility change committed by another connection after its first read', function (string $change) {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $recipient = User::factory()->create();
    $announcement = app(PrepareApplicationAnnouncement::class)->handle($admin, [
        'title_fr' => 'Information', 'title_en' => 'Information',
        'content_fr' => 'Information', 'content_en' => 'Information',
        'is_release' => false, 'send_push' => false, 'audience' => 'selected',
        'recipient_ids' => [$recipient->id],
    ]);
    app(SendApplicationAnnouncement::class)->handle($announcement);
    $delivery = $announcement->deliveries()->sole();
    $default = config('database.default');
    config()->set('database.connections.announcement_race', config('database.connections.'.$default));
    $changed = false;
    DB::listen(function (QueryExecuted $query) use (&$changed, $recipient, $change): void {
        if ($changed || $query->connectionName === 'announcement_race'
            || ! str_starts_with($query->sql, 'select * from `application_announcement_deliveries`')) {
            return;
        }
        $changed = true;
        // Commit the opt-out after the worker's first read, before it locks this user.
        DB::connection('announcement_race')->transaction(function () use ($recipient, $change): void {
            $connection = DB::connection('announcement_race');
            $connection->table('users')->where('id', $recipient->id)->lockForUpdate()->first();
            if ($change === 'preference') {
                $connection->table('notification_preferences')->insert([
                    'user_id' => $recipient->id, 'category' => WebPushPreference::ApplicationAnnouncements->value,
                    'enabled' => false, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $connection->table('users')->where('id', $recipient->id)->update(['deletion_requested_at' => now()]);
            }
        });
    });
    try {
        app()->call([new DeliverApplicationAnnouncement($delivery->id), 'handle']);
        expect($changed)->toBeTrue()
            ->and($recipient->notifications()->count())->toBe(0)
            ->and($delivery->fresh()->status)->toBe('skipped');
    } finally {
        DB::purge('announcement_race');
    }
})->with(['preference', 'deletion']);
