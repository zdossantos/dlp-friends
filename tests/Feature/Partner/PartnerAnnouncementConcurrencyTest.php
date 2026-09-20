<?php

namespace Tests\Feature\Partner;

use App\Actions\DeletePartnerAnnouncementDraft;
use App\Actions\DeliverPartnerAnnouncement;
use App\Actions\StartPartnerAnnouncement;
use App\Actions\SubmitPartnerAnnouncement;
use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerNotificationPreference;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class PartnerAnnouncementConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_concurrent_starts_cannot_bypass_the_partner_cooldown_on_mysql(): void
    {
        $this->requirePcntl();
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $profile = PartnerProfile::factory()->create();
        $first = PartnerAnnouncement::factory()->for($profile)->approved()->create([
            'destination_url' => 'https://offers.example.com/first',
        ]);
        $second = PartnerAnnouncement::factory()->for($profile)->approved()->create([
            'destination_url' => 'https://offers.example.com/second',
        ]);
        $firstControl = $this->socketPair();
        $secondControl = $this->socketPair();
        $firstResultFile = $this->resultFile('partner-announcement-start-first-');
        $secondResultFile = $this->resultFile('partner-announcement-start-second-');

        DB::disconnect();

        $firstPid = pcntl_fork();
        $this->assertNotSame(-1, $firstPid);

        if ($firstPid === 0) {
            fclose($firstControl[0]);
            fclose($secondControl[0]);
            fclose($secondControl[1]);
            DB::purge();

            try {
                PartnerAnnouncement::updating(static function (PartnerAnnouncement $announcement) use ($first, $firstControl): void {
                    if ($announcement->id !== $first->id
                        || $announcement->status !== PartnerAnnouncementStatus::Sending) {
                        return;
                    }

                    fwrite($firstControl[1], 'L');

                    if (fread($firstControl[1], 1) !== 'G') {
                        throw new RuntimeException('The first start transaction was not released.');
                    }
                });

                app(StartPartnerAnnouncement::class)->handle(
                    User::query()->findOrFail($admin->id),
                    PartnerAnnouncement::query()->findOrFail($first->id),
                );
                file_put_contents($firstResultFile, json_encode(['started' => true], JSON_THROW_ON_ERROR));
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
                fwrite($secondControl[1], 'R');
                app(StartPartnerAnnouncement::class)->handle(
                    User::query()->findOrFail($admin->id),
                    PartnerAnnouncement::query()->findOrFail($second->id),
                );
                file_put_contents($secondResultFile, json_encode(['started' => true], JSON_THROW_ON_ERROR));
                exit(1);
            } catch (ValidationException $exception) {
                file_put_contents($secondResultFile, json_encode([
                    'started' => false,
                    'has_announcement_error' => array_key_exists('announcement', $exception->errors()),
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
        $this->assertSame('R', fread($secondControl[0], 1));
        usleep(300_000);
        fwrite($firstControl[0], 'G');

        pcntl_waitpid($firstPid, $firstStatus);
        pcntl_waitpid($secondPid, $secondStatus);
        fclose($firstControl[0]);
        fclose($secondControl[0]);

        DB::purge();
        DB::reconnect();

        expect([pcntl_wexitstatus($firstStatus), pcntl_wexitstatus($secondStatus)])
            ->toBe([0, 0])
            ->and($this->readResult($firstResultFile))->toBe(['started' => true])
            ->and($this->readResult($secondResultFile))->toBe([
                'started' => false,
                'has_announcement_error' => true,
            ])
            ->and($first->fresh()?->status)->toBe(PartnerAnnouncementStatus::Sending)
            ->and($second->fresh()?->status)->toBe(PartnerAnnouncementStatus::Approved);
    }

    public function test_two_concurrent_delivery_jobs_create_one_notification_and_increment_once_on_mysql(): void
    {
        $this->requirePcntl();
        config()->set('broadcasting.default', 'null');

        $recipient = User::factory()->create();
        PartnerNotificationPreference::query()->create([
            'user_id' => $recipient->id,
            'enabled' => true,
        ]);
        $announcement = PartnerAnnouncement::factory()->create([
            'status' => PartnerAnnouncementStatus::Sending,
            'destination_url' => 'https://offers.example.com/concurrent-delivery',
            'audience_prepared_at' => now(),
            'sending_started_at' => now(),
        ]);
        PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => $announcement->id,
            'prepared_count' => 1,
        ]);
        $delivery = PartnerAnnouncementDelivery::factory()
            ->for($announcement, 'announcement')
            ->for($recipient)
            ->create();
        $firstControl = $this->socketPair();
        $secondControl = $this->socketPair();
        $firstResultFile = $this->resultFile('partner-delivery-first-');
        $secondResultFile = $this->resultFile('partner-delivery-second-');

        DB::disconnect();

        $firstPid = pcntl_fork();
        $this->assertNotSame(-1, $firstPid);

        if ($firstPid === 0) {
            fclose($firstControl[0]);
            fclose($secondControl[0]);
            fclose($secondControl[1]);
            DB::purge();

            try {
                DatabaseNotification::created(static function (DatabaseNotification $notification) use ($firstControl): void {
                    fwrite($firstControl[1], 'L');

                    if (fread($firstControl[1], 1) !== 'G') {
                        throw new RuntimeException('The first delivery transaction was not released.');
                    }
                });
                app(DeliverPartnerAnnouncement::class)->handle(
                    PartnerAnnouncementDelivery::query()->findOrFail($delivery->id),
                );
                file_put_contents($firstResultFile, json_encode(['delivered' => true], JSON_THROW_ON_ERROR));
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
                fwrite($secondControl[1], 'R');
                app(DeliverPartnerAnnouncement::class)->handle(
                    PartnerAnnouncementDelivery::query()->findOrFail($delivery->id),
                );
                file_put_contents($secondResultFile, json_encode(['delivered' => true], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($secondResultFile, json_encode([
                    'error' => $exception::class.': '.$exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                exit(1);
            }
        }

        fclose($secondControl[1]);
        $this->assertSame('R', fread($secondControl[0], 1));
        usleep(300_000);
        fwrite($firstControl[0], 'G');

        pcntl_waitpid($firstPid, $firstStatus);
        pcntl_waitpid($secondPid, $secondStatus);
        fclose($firstControl[0]);
        fclose($secondControl[0]);

        DB::purge();
        DB::reconnect();

        expect([pcntl_wexitstatus($firstStatus), pcntl_wexitstatus($secondStatus)])
            ->toBe([0, 0])
            ->and($this->readResult($firstResultFile))->toBe(['delivered' => true])
            ->and($this->readResult($secondResultFile))->toBe(['delivered' => true])
            ->and($delivery->fresh()?->status)->toBe(PartnerDeliveryStatus::Delivered)
            ->and($delivery->fresh()?->attempts)->toBe(1)
            ->and($recipient->notifications()->count())->toBe(1)
            ->and($announcement->metric?->fresh()?->delivered_count)->toBe(1);
    }

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
