<?php

use App\Actions\DecideTripRegistration;
use App\Enums\TripRegistrationStatus;
use App\Models\Trip;
use App\Models\TripRegistration;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

test('two real mysql connections cannot accept two passengers into the last seat', function () {
    expect(function_exists('pcntl_fork'))->toBeTrue();
    $organizer = User::factory()->withProfile()->create();
    $trip = Trip::factory()->for($organizer, 'organizer')->create(['audience' => 'members', 'car_seats' => 1]);
    $registrations = User::factory()->withProfile()->count(2)->create()->map(fn (User $member) => $trip->registrations()->create(['user_id' => $member->id, 'status' => TripRegistrationStatus::Pending]));
    $control = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    expect($control)->toBeArray();
    foreach ($control as $socket) {
        stream_set_timeout($socket, 15);
    }
    $resultFile = tempnam(sys_get_temp_dir(), 'trip-accept-race-');
    DB::disconnect();
    $pid = pcntl_fork();
    expect($pid)->not->toBe(-1);
    if ($pid === 0) {
        fclose($control[0]);
        DB::purge();
        try {
            TripRegistration::updating(static function (TripRegistration $registration) use ($control): void {
                if ($registration->status !== TripRegistrationStatus::Accepted) {
                    return;
                }
                fwrite($control[1], 'L');
                if (fread($control[1], 1) !== 'R') {
                    throw new RuntimeException('Lock release missing.');
                }
            });
            app(DecideTripRegistration::class)->handle($organizer, $registrations[0], true, null);
            file_put_contents($resultFile, 'ok');
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultFile, $exception::class.': '.$exception->getMessage());
            exit(1);
        }
    }
    fclose($control[1]);
    try {
        expect(fread($control[0], 1))->toBe('L');
        DB::reconnect();
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            app(DecideTripRegistration::class)->handle($organizer, $registrations[1], true, null);
            test()->fail('Concurrent acceptance must wait on the shared trip/account lock.');
        } catch (QueryException $exception) {
            expect((int) ($exception->errorInfo[1] ?? 0))->toBe(1205);
        }
    } finally {
        fwrite($control[0], 'R');
        pcntl_waitpid($pid, $status);
        fclose($control[0]);
        DB::purge();
    }
    expect(pcntl_wexitstatus($status))->toBe(0, (string) file_get_contents($resultFile));
    unlink($resultFile);
    expect(fn () => app(DecideTripRegistration::class)->handle($organizer, $registrations[1], true, null))->toThrow(ValidationException::class);
    expect($trip->registrations()->where('status', TripRegistrationStatus::Accepted)->count())->toBe(1);
});
