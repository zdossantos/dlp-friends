<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('retains an expired trip when a ban commits after the retention snapshot', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('This lock test requires the pcntl extension.');
    }
    $target = User::factory()->withProfile()->create();
    $trip = Trip::factory()->create(['organizer_user_id' => $target->id, 'departs_at' => now()->subDays(40)]);
    $control = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    $this->assertIsArray($control);
    foreach ($control as $socket) {
        stream_set_timeout($socket, 15);
    }
    DB::disconnect();
    $pid = pcntl_fork();
    $this->assertNotSame(-1, $pid);
    if ($pid === 0) {
        fclose($control[0]);
        DB::purge();
        if (fread($control[1], 1) !== 'S') {
            exit(1);
        }
        User::query()->whereKey($target->id)->update(['status' => 'banned']);
        fwrite($control[1], 'B');
        exit(0);
    }
    fclose($control[1]);
    DB::reconnect();
    $paused = false;
    DB::connection()->beforeExecuting(function (string $query) use (&$paused, $control): void {
        if (! $paused && str_contains($query, 'from `users`') && str_contains($query, 'for update')) {
            $paused = true;
            fwrite($control[0], 'S');
            expect(fread($control[0], 1))->toBe('B');
        }
    });
    try {
        $this->artisan('trips:purge-expired')->assertSuccessful();
        expect($paused)->toBeTrue();
        $this->assertDatabaseHas('trips', ['id' => $trip->id]);
    } finally {
        fclose($control[0]);
        pcntl_waitpid($pid, $status);
        DB::purge();
    }
});
