<?php

namespace Tests\Feature\Partner;

use App\Actions\DeletePartnerAnnouncementDraft;
use App\Actions\SubmitPartnerAnnouncement;
use App\Enums\PartnerAnnouncementStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class PartnerAnnouncementConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_concurrent_submission_is_never_deleted_by_a_stale_draft_request_on_mysql(): void
    {
        $this->requirePcntl();

        $partner = User::factory()->partnerOnly()->create();
        $profile = PartnerProfile::factory()->for($partner)->create();
        $draft = PartnerAnnouncement::factory()->for($profile)->create([
            'destination_url' => 'https://example.com/concurrent',
        ]);
        $submitControl = $this->socketPair();
        $deleteControl = $this->socketPair();
        $submitResultFile = $this->resultFile('partner-announcement-submit-');
        $deleteResultFile = $this->resultFile('partner-announcement-delete-');

        DB::disconnect();

        $submitPid = pcntl_fork();
        $this->assertNotSame(-1, $submitPid);

        if ($submitPid === 0) {
            fclose($submitControl[0]);
            fclose($deleteControl[0]);
            fclose($deleteControl[1]);
            DB::purge();

            try {
                PartnerAnnouncement::updating(static function (PartnerAnnouncement $announcement) use ($draft, $submitControl): void {
                    if ($announcement->id !== $draft->id || ! $announcement->isDirty('status')) {
                        return;
                    }

                    fwrite($submitControl[1], 'L');

                    if (fread($submitControl[1], 1) !== 'G') {
                        throw new RuntimeException('The submission transaction was not released.');
                    }
                });

                app(SubmitPartnerAnnouncement::class)->handle(
                    User::query()->findOrFail($partner->id),
                    PartnerAnnouncement::query()->findOrFail($draft->id),
                );
                file_put_contents($submitResultFile, json_encode([
                    'submitted' => true,
                ], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($submitResultFile, json_encode([
                    'error' => $exception::class.': '.$exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                exit(1);
            }
        }

        fclose($submitControl[1]);
        $this->assertSame('L', fread($submitControl[0], 1));

        $deletePid = pcntl_fork();
        $this->assertNotSame(-1, $deletePid);

        if ($deletePid === 0) {
            fclose($submitControl[0]);
            fclose($deleteControl[0]);
            DB::purge();

            try {
                $staleDraft = PartnerAnnouncement::query()->findOrFail($draft->id);
                fwrite($deleteControl[1], 'R');
                app(DeletePartnerAnnouncementDraft::class)->handle(
                    User::query()->findOrFail($partner->id),
                    $staleDraft,
                );
                file_put_contents($deleteResultFile, json_encode([
                    'deleted' => true,
                ], JSON_THROW_ON_ERROR));
                exit(1);
            } catch (ValidationException $exception) {
                file_put_contents($deleteResultFile, json_encode([
                    'deleted' => false,
                    'has_announcement_error' => array_key_exists('announcement', $exception->errors()),
                ], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($deleteResultFile, json_encode([
                    'error' => $exception::class.': '.$exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                exit(1);
            }
        }

        fclose($deleteControl[1]);
        $this->assertSame('R', fread($deleteControl[0], 1));
        usleep(300_000);
        fwrite($submitControl[0], 'G');

        pcntl_waitpid($submitPid, $submitStatus);
        pcntl_waitpid($deletePid, $deleteStatus);
        fclose($submitControl[0]);
        fclose($deleteControl[0]);

        DB::purge();
        DB::reconnect();

        expect([pcntl_wexitstatus($submitStatus), pcntl_wexitstatus($deleteStatus)])
            ->toBe([0, 0])
            ->and($this->readResult($submitResultFile))->toBe(['submitted' => true])
            ->and($this->readResult($deleteResultFile))->toBe([
                'deleted' => false,
                'has_announcement_error' => true,
            ])
            ->and($draft->fresh())->not->toBeNull()
            ->and($draft->fresh()?->status)->toBe(PartnerAnnouncementStatus::PendingApproval);
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
