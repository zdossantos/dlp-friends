<?php

use App\Actions\ReportTripDeparture;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripDepartureReport;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('serializes simultaneous late departure reports into one open dossier', function () {
    if (! function_exists('pcntl_fork')) {
        $this->fail('This lock test requires the pcntl extension.');
    }

    $members = User::factory()->withProfile()->count(2)->create();
    $trip = Trip::factory()->for($members[0], 'organizer')->create();
    $departure = TripDeparture::factory()->for($trip)->for($members[1], 'user')->create(['scheduled_departure_at' => now()->addHour(), 'left_at' => now()]);
    $control = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    $this->assertIsArray($control);
    foreach ($control as $socket) {
        stream_set_timeout($socket, 15);
    }
    $resultFile = tempnam(sys_get_temp_dir(), 'conversation-report-race-');
    $this->assertIsString($resultFile);
    DB::disconnect();

    $pid = pcntl_fork();
    $this->assertNotSame(-1, $pid);
    if ($pid === 0) {
        fclose($control[0]);
        DB::purge();
        try {
            TripDepartureReport::creating(static function () use ($control): void {
                fwrite($control[1], 'L');
                if (fread($control[1], 1) !== 'R') {
                    throw new RuntimeException('The participant lock was not released.');
                }
            });
            app(ReportTripDeparture::class)->handle(
                User::query()->findOrFail($members[0]->id),
                TripDeparture::query()->findOrFail($departure->id),
                'Départ tardif',
            );
            file_put_contents($resultFile, 'ok');
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultFile, $exception::class.': '.$exception->getMessage());
            exit(1);
        }
    }

    fclose($control[1]);
    $this->assertSame('L', fread($control[0], 1));
    DB::reconnect();
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
    try {
        app(ReportTripDeparture::class)->handle($members[0], $departure, 'Départ tardif');
        $this->fail('The duplicate report must wait for the participant lock.');
    } catch (QueryException $exception) {
        $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
    }

    fwrite($control[0], 'R');
    pcntl_waitpid($pid, $status);
    fclose($control[0]);
    DB::purge();
    DB::reconnect();

    $this->assertSame(0, pcntl_wexitstatus($status), (string) file_get_contents($resultFile));
    unlink($resultFile);
    $this->assertDatabaseCount('trip_departure_reports', 1);
    app(ReportTripDeparture::class)->handle($members[0], $departure, 'Reprise');
    $this->assertDatabaseCount('trip_departure_reports', 1);
});
