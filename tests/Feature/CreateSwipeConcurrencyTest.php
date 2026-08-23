<?php

namespace Tests\Feature;

use App\Actions\CreateSwipe;
use App\Enums\SwipeDecision;
use App\Models\MemberMatch;
use App\Models\Profile;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class CreateSwipeConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_overlapping_opposite_likes_create_exactly_one_match_on_mysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This lock test requires two real MySQL connections.');
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('This lock test requires the pcntl extension.');
        }

        [$lowUser, $highUser] = [$this->member(), $this->member()];
        $firstControl = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $secondControl = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $this->assertIsArray($firstControl);
        $this->assertIsArray($secondControl);

        foreach ([...$firstControl, ...$secondControl] as $socket) {
            stream_set_timeout($socket, 15);
        }

        $firstResultFile = tempnam(sys_get_temp_dir(), 'swipe-concurrency-first-');
        $secondResultFile = tempnam(sys_get_temp_dir(), 'swipe-concurrency-second-');
        $this->assertIsString($firstResultFile);
        $this->assertIsString($secondResultFile);

        DB::disconnect();

        $firstPid = pcntl_fork();
        $this->assertNotSame(-1, $firstPid);

        if ($firstPid === 0) {
            fclose($firstControl[0]);
            fclose($secondControl[0]);
            fclose($secondControl[1]);
            DB::purge();

            try {
                Swipe::creating(static function () use ($firstControl): void {
                    fwrite($firstControl[1], 'L');

                    if (fread($firstControl[1], 1) !== 'R') {
                        throw new RuntimeException('The first transaction was not released.');
                    }
                });
                $match = app(CreateSwipe::class)->handle(
                    User::query()->findOrFail($lowUser->id),
                    User::query()->findOrFail($highUser->id),
                    SwipeDecision::Like,
                );
                file_put_contents($firstResultFile, json_encode([
                    'matched' => $match instanceof MemberMatch,
                ], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($firstResultFile, json_encode([
                    'error' => $exception::class.': '.$exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                exit(1);
            }
        }

        fclose($firstControl[1]);
        $this->assertSame('L', fread($firstControl[0], 1));

        $secondPid = pcntl_fork();
        $this->assertNotSame(-1, $secondPid);

        if ($secondPid === 0) {
            fclose($firstControl[0]);
            fclose($secondControl[0]);
            DB::purge();

            try {
                DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

                try {
                    app(CreateSwipe::class)->handle(
                        User::query()->findOrFail($highUser->id),
                        User::query()->findOrFail($lowUser->id),
                        SwipeDecision::Like,
                    );
                    throw new RuntimeException('The opposite like did not wait for the pair lock.');
                } catch (QueryException $exception) {
                    if ((int) ($exception->errorInfo[1] ?? 0) !== 1205) {
                        throw $exception;
                    }
                }

                fwrite($secondControl[1], 'B');

                if (fread($secondControl[1], 1) !== 'R') {
                    throw new RuntimeException('The blocked transaction was not released for retry.');
                }

                DB::purge();
                $match = app(CreateSwipe::class)->handle(
                    User::query()->findOrFail($highUser->id),
                    User::query()->findOrFail($lowUser->id),
                    SwipeDecision::Like,
                );
                file_put_contents($secondResultFile, json_encode([
                    'blocked' => true,
                    'matched' => $match instanceof MemberMatch,
                ], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($secondResultFile, json_encode([
                    'error' => $exception::class.': '.$exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                exit(1);
            }
        }

        fclose($secondControl[1]);
        $this->assertSame('B', fread($secondControl[0], 1));
        fwrite($firstControl[0], 'R');
        pcntl_waitpid($firstPid, $firstStatus);
        fwrite($secondControl[0], 'R');
        pcntl_waitpid($secondPid, $secondStatus);
        fclose($firstControl[0]);
        fclose($secondControl[0]);

        $statuses = [
            pcntl_wexitstatus($firstStatus),
            pcntl_wexitstatus($secondStatus),
        ];

        DB::purge();
        DB::reconnect();

        $firstResult = json_decode((string) file_get_contents($firstResultFile), true, flags: JSON_THROW_ON_ERROR);
        $secondResult = json_decode((string) file_get_contents($secondResultFile), true, flags: JSON_THROW_ON_ERROR);
        unlink($firstResultFile);
        unlink($secondResultFile);

        expect($statuses)->toBe([0, 0])
            ->and($firstResult)->toBe(['matched' => false])
            ->and($secondResult)->toBe(['blocked' => true, 'matched' => true]);
        $this->assertDatabaseCount('swipes', 2);
        $this->assertDatabaseCount('matches', 1);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        Profile::factory()->complete()->for($user)->create();

        return $user;
    }
}
