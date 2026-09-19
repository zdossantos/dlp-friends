<?php

namespace Tests\Feature\Admin;

use App\Actions\ApprovePartnerProfileRevision;
use App\Actions\UpdatePublishedPartnerOrder;
use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class PartnerProfileModerationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_approvals_receive_distinct_consecutive_positions_on_mysql(): void
    {
        $this->requirePcntl();

        $admin = User::factory()->admin()->create();
        PartnerProfile::factory()->published()->create(['position' => 1]);
        $firstRevision = $this->pendingRevision();
        $secondRevision = $this->pendingRevision();

        $firstControl = $this->socketPair();
        $secondControl = $this->socketPair();
        $firstResultFile = $this->resultFile('partner-approval-first-');
        $secondResultFile = $this->resultFile('partner-approval-second-');

        DB::disconnect();

        $firstPid = $this->forkApproval(
            $firstControl,
            $secondControl,
            $firstResultFile,
            $admin->id,
            $firstRevision->id,
        );
        $secondPid = $this->forkApproval(
            $secondControl,
            $firstControl,
            $secondResultFile,
            $admin->id,
            $secondRevision->id,
        );

        fclose($firstControl[1]);
        fclose($secondControl[1]);
        $this->assertSame('R', fread($firstControl[0], 1));
        $this->assertSame('R', fread($secondControl[0], 1));
        fwrite($firstControl[0], 'G');
        fwrite($secondControl[0], 'G');

        pcntl_waitpid($firstPid, $firstStatus);
        pcntl_waitpid($secondPid, $secondStatus);
        fclose($firstControl[0]);
        fclose($secondControl[0]);

        DB::purge();
        DB::reconnect();

        $results = [
            $this->readResult($firstResultFile),
            $this->readResult($secondResultFile),
        ];
        $positions = PartnerProfile::query()
            ->published()
            ->orderBy('position')
            ->pluck('position')
            ->all();

        expect([pcntl_wexitstatus($firstStatus), pcntl_wexitstatus($secondStatus)])
            ->toBe([0, 0])
            ->and($results)->toBe([['approved' => true], ['approved' => true]])
            ->and($positions)->toBe([1, 2, 3]);
    }

    public function test_unpublish_and_reorder_complete_without_error_or_incoherent_positions_on_mysql(): void
    {
        $this->requirePcntl();

        [$first, $second, $target] = PartnerProfile::factory()
            ->count(3)
            ->sequence(['position' => 1], ['position' => 2], ['position' => 3])
            ->published()
            ->create();
        $orderedIds = [$second->id, $first->id];

        $unpublishControl = $this->socketPair();
        $reorderControl = $this->socketPair();
        $unpublishResultFile = $this->resultFile('partner-unpublish-');
        $reorderResultFile = $this->resultFile('partner-reorder-');

        DB::disconnect();

        $unpublishPid = pcntl_fork();
        $this->assertNotSame(-1, $unpublishPid);

        if ($unpublishPid === 0) {
            fclose($unpublishControl[0]);
            fclose($reorderControl[0]);
            fclose($reorderControl[1]);
            DB::purge();

            try {
                PartnerProfile::updating(static function (PartnerProfile $profile) use ($target, $unpublishControl): void {
                    if ($profile->id !== $target->id || ! $profile->isDirty('is_published')) {
                        return;
                    }

                    fwrite($unpublishControl[1], 'L');

                    if (fread($unpublishControl[1], 1) !== 'G') {
                        throw new RuntimeException('The unpublish transaction was not released.');
                    }
                });

                app(ApprovePartnerProfileRevision::class)->unpublish(
                    PartnerProfile::query()->findOrFail($target->id),
                );
                file_put_contents($unpublishResultFile, json_encode([
                    'unpublished' => true,
                ], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($unpublishResultFile, json_encode([
                    'error' => $exception::class.': '.$exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                exit(1);
            }
        }

        fclose($unpublishControl[1]);
        $this->assertSame('L', fread($unpublishControl[0], 1));

        $reorderPid = pcntl_fork();
        $this->assertNotSame(-1, $reorderPid);

        if ($reorderPid === 0) {
            fclose($unpublishControl[0]);
            fclose($reorderControl[0]);
            DB::purge();

            try {
                fwrite($reorderControl[1], 'R');
                app(UpdatePublishedPartnerOrder::class)->handle($orderedIds);
                file_put_contents($reorderResultFile, json_encode([
                    'reordered' => true,
                ], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($reorderResultFile, json_encode([
                    'error' => $exception::class.': '.$exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                exit(1);
            }
        }

        fclose($reorderControl[1]);
        $this->assertSame('R', fread($reorderControl[0], 1));
        usleep(300_000);
        fwrite($unpublishControl[0], 'G');

        pcntl_waitpid($unpublishPid, $unpublishStatus);
        pcntl_waitpid($reorderPid, $reorderStatus);
        fclose($unpublishControl[0]);
        fclose($reorderControl[0]);

        DB::purge();
        DB::reconnect();

        $results = [
            $this->readResult($unpublishResultFile),
            $this->readResult($reorderResultFile),
        ];
        $published = PartnerProfile::query()
            ->published()
            ->orderBy('position')
            ->get(['id', 'position']);

        expect([pcntl_wexitstatus($unpublishStatus), pcntl_wexitstatus($reorderStatus)])
            ->toBe([0, 0])
            ->and($results)->toBe([['unpublished' => true], ['reordered' => true]])
            ->and($target->fresh()->is_published)->toBeFalse()
            ->and($published->pluck('id')->all())->toBe($orderedIds)
            ->and($published->pluck('position')->all())->toBe([1, 2]);
    }

    /**
     * @param  array<int, resource>  $control
     * @param  array<int, resource>  $otherControl
     */
    private function forkApproval(
        array $control,
        array $otherControl,
        string $resultFile,
        int $adminId,
        int $revisionId,
    ): int {
        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid);

        if ($pid !== 0) {
            return $pid;
        }

        fclose($control[0]);

        foreach ($otherControl as $socket) {
            fclose($socket);
        }

        DB::purge();

        try {
            PartnerProfileRevision::updating(static function (PartnerProfileRevision $revision) use ($revisionId): void {
                if ($revision->id === $revisionId && $revision->isDirty('status')) {
                    usleep(300_000);
                }
            });
            fwrite($control[1], 'R');

            if (fread($control[1], 1) !== 'G') {
                throw new RuntimeException('The approval transaction was not started.');
            }

            app(ApprovePartnerProfileRevision::class)->handle(
                User::query()->findOrFail($adminId),
                PartnerProfileRevision::query()->findOrFail($revisionId),
            );
            file_put_contents($resultFile, json_encode([
                'approved' => true,
            ], JSON_THROW_ON_ERROR));
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultFile, json_encode([
                'error' => $exception::class.': '.$exception->getMessage(),
            ], JSON_THROW_ON_ERROR));
            exit(1);
        }
    }

    private function pendingRevision(): PartnerProfileRevision
    {
        $profile = PartnerProfile::factory()->create(['position' => 0]);

        return PartnerProfileRevision::factory()->for($profile)->create([
            'status' => PartnerRevisionStatus::PendingApproval,
            'submitted_at' => now(),
            'draft_key' => null,
        ]);
    }

    /** @return array<int, resource> */
    private function socketPair(): array
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $this->assertIsArray($sockets);

        foreach ($sockets as $socket) {
            stream_set_timeout($socket, 15);
        }

        return $sockets;
    }

    private function resultFile(string $prefix): string
    {
        $file = tempnam(sys_get_temp_dir(), $prefix);
        $this->assertIsString($file);

        return $file;
    }

    /** @return array<string, bool|string> */
    private function readResult(string $file): array
    {
        $result = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        unlink($file);

        return $result;
    }

    private function requirePcntl(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('This lock test requires the pcntl extension.');
        }
    }
}
