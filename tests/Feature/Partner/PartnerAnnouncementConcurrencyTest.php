<?php

namespace Tests\Feature\Partner;

use App\Actions\DeletePartnerAnnouncementDraft;
use App\Actions\DeliverPartnerAnnouncement;
use App\Actions\RequestAccountDeletion;
use App\Actions\StartPartnerAnnouncement;
use App\Actions\SubmitPartnerAnnouncement;
use App\Actions\SyncManageableUserRoles;
use App\Actions\UpdatePartnerNotificationPreference;
use App\Enums\PartnerAnnouncementStatus;
use App\Enums\PartnerDeliveryStatus;
use App\Enums\RoleAuditAction;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\PartnerNotificationPreference;
use App\Models\PartnerProfile;
use App\Models\RoleAudit;
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

    public function test_a_committed_consent_withdrawal_prevents_a_concurrent_delivery_on_mysql(): void
    {
        $this->assertCommittedEligibilityMutationPreventsDelivery('consent');
    }

    public function test_a_committed_user_role_removal_prevents_a_concurrent_delivery_on_mysql(): void
    {
        $this->assertCommittedEligibilityMutationPreventsDelivery('role');
    }

    public function test_a_committed_deletion_request_prevents_a_concurrent_delivery_on_mysql(): void
    {
        $this->assertCommittedEligibilityMutationPreventsDelivery('deletion');
    }

    public function test_sender_deletion_and_delivery_complete_without_deadlock_or_double_counting_on_mysql(): void
    {
        $this->requirePcntl();
        Queue::fake();

        $sender = User::factory()->partner()->create();
        $profile = PartnerProfile::factory()->for($sender)->published()->create();
        $recipient = User::factory()->create();
        PartnerNotificationPreference::query()->create([
            'user_id' => $recipient->id,
            'enabled' => true,
        ]);
        $announcement = PartnerAnnouncement::factory()->for($profile)->create([
            'status' => PartnerAnnouncementStatus::Sending,
            'destination_url' => 'https://offers.example.com/sender-deletion',
            'audience_prepared_at' => now(),
            'sending_started_at' => now(),
        ]);
        $metric = PartnerAnnouncementMetric::query()->create([
            'partner_announcement_id' => $announcement->id,
            'prepared_count' => 1,
        ]);
        $delivery = PartnerAnnouncementDelivery::factory()
            ->for($announcement, 'announcement')
            ->for($recipient)
            ->create();
        $deliveryControl = $this->socketPair();
        $deletionControl = $this->socketPair();
        $deliveryResultFile = $this->resultFile('partner-sender-deletion-delivery-');
        $deletionResultFile = $this->resultFile('partner-sender-deletion-request-');

        DB::disconnect();

        $deliveryPid = pcntl_fork();
        $this->assertNotSame(-1, $deliveryPid);

        if ($deliveryPid === 0) {
            fclose($deliveryControl[0]);
            fclose($deletionControl[0]);
            fclose($deletionControl[1]);
            DB::purge();

            try {
                PartnerAnnouncementDelivery::updating(
                    static function (PartnerAnnouncementDelivery $candidate) use ($delivery, $deliveryControl): void {
                        if ($candidate->id !== $delivery->id
                            || $candidate->status !== PartnerDeliveryStatus::Delivered) {
                            return;
                        }

                        fwrite($deliveryControl[1], 'L');

                        if (fread($deliveryControl[1], 1) !== 'G') {
                            throw new RuntimeException('The delivery transaction was not released.');
                        }
                    },
                );
                app(DeliverPartnerAnnouncement::class)->handle(
                    PartnerAnnouncementDelivery::query()->findOrFail($delivery->id),
                );
                file_put_contents($deliveryResultFile, json_encode(['delivered' => true], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($deliveryResultFile, json_encode([
                    'error' => $exception::class.': '.$exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                exit(1);
            }
        }

        fclose($deliveryControl[1]);
        $this->assertSame('L', fread($deliveryControl[0], 1));

        $deletionPid = pcntl_fork();
        $this->assertNotSame(-1, $deletionPid);

        if ($deletionPid === 0) {
            fclose($deliveryControl[0]);
            fclose($deletionControl[0]);
            DB::purge();

            try {
                PartnerAnnouncement::updated(
                    static function (PartnerAnnouncement $candidate) use ($announcement, $deletionControl): void {
                        if ($candidate->id !== $announcement->id
                            || $candidate->status !== PartnerAnnouncementStatus::Cancelled) {
                            return;
                        }

                        fwrite($deletionControl[1], 'R');

                        if (fread($deletionControl[1], 1) !== 'G') {
                            throw new RuntimeException('The sender deletion transaction was not released.');
                        }
                    },
                );
                app(RequestAccountDeletion::class)->handle(
                    User::query()->findOrFail($sender->id),
                );
                file_put_contents($deletionResultFile, json_encode(['deleted' => true], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($deletionResultFile, json_encode([
                    'error' => $exception::class.': '.$exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                exit(1);
            }
        }

        fclose($deletionControl[1]);
        $this->assertSame('R', fread($deletionControl[0], 1));
        fwrite($deletionControl[0], 'G');
        usleep(300_000);
        fwrite($deliveryControl[0], 'G');

        pcntl_waitpid($deliveryPid, $deliveryStatus);
        pcntl_waitpid($deletionPid, $deletionStatus);
        fclose($deliveryControl[0]);
        fclose($deletionControl[0]);

        DB::purge();
        DB::reconnect();

        expect([pcntl_wexitstatus($deliveryStatus), pcntl_wexitstatus($deletionStatus)])
            ->toBe([0, 0])
            ->and($this->readResult($deliveryResultFile))->toBe(['delivered' => true])
            ->and($this->readResult($deletionResultFile))->toBe(['deleted' => true])
            ->and($profile->fresh()?->is_published)->toBeFalse()
            ->and($announcement->fresh()?->status)->toBe(PartnerAnnouncementStatus::Cancelled)
            ->and($delivery->fresh()?->status)->toBe(PartnerDeliveryStatus::Delivered)
            ->and($delivery->fresh()?->attempts)->toBe(1)
            ->and($recipient->notifications()->count())->toBe(1)
            ->and($metric->fresh()?->delivered_count)->toBe(1);

        app(DeliverPartnerAnnouncement::class)->handle($delivery->fresh());

        expect($recipient->notifications()->count())->toBe(1)
            ->and($metric->fresh()?->delivered_count)->toBe(1);
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

    private function assertCommittedEligibilityMutationPreventsDelivery(string $mutation): void
    {
        $this->requirePcntl();
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->create();
        PartnerNotificationPreference::query()->create([
            'user_id' => $recipient->id,
            'enabled' => true,
        ]);
        $announcement = PartnerAnnouncement::factory()->create([
            'status' => PartnerAnnouncementStatus::Sending,
            'destination_url' => 'https://offers.example.com/eligibility-lock',
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
        $mutationControl = $this->socketPair();
        $deliveryControl = $this->socketPair();
        $mutationResultFile = $this->resultFile("partner-eligibility-{$mutation}-");
        $deliveryResultFile = $this->resultFile('partner-eligibility-delivery-');

        DB::disconnect();

        $mutationPid = pcntl_fork();
        $this->assertNotSame(-1, $mutationPid);

        if ($mutationPid === 0) {
            fclose($mutationControl[0]);
            fclose($deliveryControl[0]);
            fclose($deliveryControl[1]);
            DB::purge();

            try {
                $pauseBeforeCommit = static function () use ($mutationControl): void {
                    fwrite($mutationControl[1], 'L');

                    if (fread($mutationControl[1], 1) !== 'G') {
                        throw new RuntimeException('The eligibility mutation was not released.');
                    }
                };

                match ($mutation) {
                    'consent' => PartnerNotificationPreference::updated(
                        static function (PartnerNotificationPreference $preference) use ($recipient, $pauseBeforeCommit): void {
                            if ($preference->user_id === $recipient->id && $preference->enabled === false) {
                                $pauseBeforeCommit();
                            }
                        },
                    ),
                    'role' => RoleAudit::created(
                        static function (RoleAudit $audit) use ($recipient, $pauseBeforeCommit): void {
                            if ($audit->target_user_id === $recipient->id
                                && $audit->role === RoleName::User
                                && $audit->action === RoleAuditAction::Removed) {
                                $pauseBeforeCommit();
                            }
                        },
                    ),
                    'deletion' => User::updated(
                        static function (User $user) use ($recipient, $pauseBeforeCommit): void {
                            if ($user->id === $recipient->id && $user->status === UserStatus::PendingDeletion) {
                                $pauseBeforeCommit();
                            }
                        },
                    ),
                };

                match ($mutation) {
                    'consent' => app(UpdatePartnerNotificationPreference::class)->handle(
                        User::query()->findOrFail($recipient->id),
                        false,
                    ),
                    'role' => app(SyncManageableUserRoles::class)->handle(
                        User::query()->findOrFail($admin->id),
                        User::query()->findOrFail($recipient->id),
                        [],
                    ),
                    'deletion' => app(RequestAccountDeletion::class)->handle(
                        User::query()->findOrFail($recipient->id),
                    ),
                };
                file_put_contents($mutationResultFile, json_encode(['committed' => true], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($mutationResultFile, json_encode([
                    'error' => $exception::class.': '.$exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                exit(1);
            }
        }

        fclose($mutationControl[1]);
        $this->assertSame('L', fread($mutationControl[0], 1));

        $deliveryPid = pcntl_fork();
        $this->assertNotSame(-1, $deliveryPid);

        if ($deliveryPid === 0) {
            fclose($mutationControl[0]);
            fclose($deliveryControl[0]);
            DB::purge();

            try {
                fwrite($deliveryControl[1], 'R');
                app(DeliverPartnerAnnouncement::class)->handle(
                    PartnerAnnouncementDelivery::query()->findOrFail($delivery->id),
                );
                file_put_contents($deliveryResultFile, json_encode(['handled' => true], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($deliveryResultFile, json_encode([
                    'error' => $exception::class.': '.$exception->getMessage(),
                ], JSON_THROW_ON_ERROR));
                exit(1);
            }
        }

        fclose($deliveryControl[1]);
        $this->assertSame('R', fread($deliveryControl[0], 1));
        usleep(300_000);
        fwrite($mutationControl[0], 'G');

        pcntl_waitpid($mutationPid, $mutationStatus);
        pcntl_waitpid($deliveryPid, $deliveryStatus);
        fclose($mutationControl[0]);
        fclose($deliveryControl[0]);

        DB::purge();
        DB::reconnect();

        expect([pcntl_wexitstatus($mutationStatus), pcntl_wexitstatus($deliveryStatus)])
            ->toBe([0, 0])
            ->and($this->readResult($mutationResultFile))->toBe(['committed' => true])
            ->and($this->readResult($deliveryResultFile))->toBe(['handled' => true])
            ->and($delivery->fresh()?->status)->toBe(PartnerDeliveryStatus::Skipped)
            ->and($delivery->fresh()?->attempts)->toBe(1)
            ->and($recipient->notifications()->count())->toBe(0)
            ->and($announcement->metric?->fresh()?->delivered_count)->toBe(0);
    }
}
