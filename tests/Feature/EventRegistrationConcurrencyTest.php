<?php

namespace Tests\Feature;

use App\Actions\RegisterForEvent;
use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class EventRegistrationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_automatic_joins_cannot_take_one_remaining_place(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('This lock test requires the pcntl extension.');
        }

        $event = Event::factory()->create(['capacity' => 2]);
        $members = User::factory()->withProfile()->count(2)->create();
        $control = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $this->assertIsArray($control);
        foreach ($control as $socket) {
            stream_set_timeout($socket, 15);
        }
        $resultFile = tempnam(sys_get_temp_dir(), 'event-registration-race-');
        $this->assertIsString($resultFile);
        DB::disconnect();

        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid);
        if ($pid === 0) {
            fclose($control[0]);
            DB::purge();
            try {
                EventRegistration::creating(static function () use ($control): void {
                    fwrite($control[1], 'L');
                    if (fread($control[1], 1) !== 'R') {
                        throw new RuntimeException('The event lock was not released.');
                    }
                });
                app(RegisterForEvent::class)->handle(
                    User::query()->findOrFail($members[0]->id),
                    Event::query()->findOrFail($event->id),
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
            app(RegisterForEvent::class)->handle($members[1], $event);
            $this->fail('The second registration should wait for the event lock.');
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
        $this->assertSame(1, EventRegistration::query()->where('status', EventRegistrationStatus::Accepted)->count());
    }
}
