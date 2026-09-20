<?php

namespace Tests\Feature\Partner;

use App\Actions\RecordPartnerAnnouncementClick;
use App\Enums\PartnerDeliveryStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerAnnouncementDelivery;
use App\Models\PartnerAnnouncementMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class PartnerAnnouncementEngagementTest extends TestCase
{
    use DatabaseMigrations;

    public function test_reading_a_partner_notification_counts_once_and_uses_the_opaque_click_route(): void
    {
        [$member, $announcement, $notification, $delivery] = $this->deliveredNotification();

        $this->actingAs($member)
            ->withHeader('X-Inertia', 'true')
            ->patch(route('notifications.read', $notification))
            ->assertStatus(409)
            ->assertHeader(
                'X-Inertia-Location',
                route('partner-announcements.click', $delivery->click_token, absolute: false),
            );
        $this->actingAs($member)
            ->withHeader('X-Inertia', 'true')
            ->patch(route('notifications.read', $notification))
            ->assertStatus(409)
            ->assertHeader(
                'X-Inertia-Location',
                route('partner-announcements.click', $delivery->click_token, absolute: false),
            );

        expect($notification->fresh()?->read_at)->not->toBeNull()
            ->and($delivery->fresh()?->read_at)->not->toBeNull()
            ->and($announcement->metric?->fresh()?->read_count)->toBe(1);
    }

    public function test_mark_all_read_counts_each_unread_partner_delivery_once(): void
    {
        [$member, $firstAnnouncement, $firstNotification, $firstDelivery] = $this->deliveredNotification();
        [, $secondAnnouncement, $secondNotification, $secondDelivery] = $this->deliveredNotification($member);
        $secondNotification->markAsRead();
        $secondDelivery->update(['read_at' => now()]);
        $secondAnnouncement->metric()->update(['read_count' => 1]);

        $this->actingAs($member)->patch(route('notifications.read-all'))->assertRedirect(route('notifications.index'));
        $this->actingAs($member)->patch(route('notifications.read-all'))->assertRedirect(route('notifications.index'));

        expect($firstNotification->fresh()?->read_at)->not->toBeNull()
            ->and($firstDelivery->fresh()?->read_at)->not->toBeNull()
            ->and($firstAnnouncement->metric?->fresh()?->read_count)->toBe(1)
            ->and($secondNotification->fresh()?->read_at)->not->toBeNull()
            ->and($secondDelivery->fresh()?->read_at)->not->toBeNull()
            ->and($secondAnnouncement->metric?->fresh()?->read_count)->toBe(1);
    }

    public function test_read_metric_requires_both_notification_and_delivery_to_be_unread(): void
    {
        [$member, $announcement, $notification, $delivery] = $this->deliveredNotification();
        $notification->markAsRead();

        $this->actingAs($member)
            ->withHeader('X-Inertia', 'true')
            ->patch(route('notifications.read', $notification))
            ->assertStatus(409)
            ->assertHeader(
                'X-Inertia-Location',
                route('partner-announcements.click', $delivery->click_token, absolute: false),
            );

        expect($delivery->fresh()?->read_at)->toBeNull()
            ->and($announcement->metric?->fresh()?->read_count)->toBe(0);
    }

    public function test_only_the_owner_can_dismiss_a_partner_notification_and_it_counts_once(): void
    {
        [$member, $announcement, $notification, $delivery] = $this->deliveredNotification();
        $attacker = User::factory()->withProfile()->create();

        $this->actingAs($attacker)
            ->delete(route('notifications.partner-announcements.dismiss', $notification))
            ->assertNotFound();
        expect($delivery->fresh()?->dismissed_at)->toBeNull()
            ->and($announcement->metric?->fresh()?->dismissed_count)->toBe(0);

        $this->actingAs($member)
            ->delete(route('notifications.partner-announcements.dismiss', $notification))
            ->assertRedirect(route('notifications.index'));
        $this->actingAs($member)
            ->delete(route('notifications.partner-announcements.dismiss', $notification))
            ->assertNotFound();

        expect($delivery->fresh()?->dismissed_at)->not->toBeNull()
            ->and($announcement->metric?->fresh()?->dismissed_count)->toBe(1)
            ->and(DatabaseNotification::query()->find($notification->id))->toBeNull();
    }

    public function test_dismissal_is_durable_before_the_notification_is_deleted(): void
    {
        [$member, $announcement, $notification, $delivery] = $this->deliveredNotification();
        $failDeletion = true;
        DatabaseNotification::deleting(static function () use (&$failDeletion): void {
            if ($failDeletion) {
                throw new RuntimeException('notification storage unavailable');
            }
        });

        try {
            $this->actingAs($member)
                ->delete(route('notifications.partner-announcements.dismiss', $notification))
                ->assertServerError();
        } finally {
            $failDeletion = false;
        }

        expect($delivery->fresh()?->dismissed_at)->not->toBeNull()
            ->and($announcement->metric?->fresh()?->dismissed_count)->toBe(1)
            ->and(DatabaseNotification::query()->find($notification->id))->not->toBeNull();

        $this->actingAs($member)
            ->delete(route('notifications.partner-announcements.dismiss', $notification))
            ->assertRedirect(route('notifications.index'));
        expect($announcement->metric?->fresh()?->dismissed_count)->toBe(1)
            ->and(DatabaseNotification::query()->find($notification->id))->toBeNull();
    }

    public function test_each_click_counts_total_engagement_but_unique_engagement_only_once(): void
    {
        [, $announcement, , $delivery] = $this->deliveredNotification();

        expect(app(RecordPartnerAnnouncementClick::class)->handle($delivery->click_token))
            ->toBe('https://offers.example.com/frozen-destination')
            ->and(app(RecordPartnerAnnouncementClick::class)->handle($delivery->click_token))
            ->toBe('https://offers.example.com/frozen-destination')
            ->and($delivery->fresh()?->first_clicked_at)->not->toBeNull()
            ->and($delivery->fresh()?->click_count)->toBe(2)
            ->and($announcement->metric?->fresh()?->unique_click_count)->toBe(1)
            ->and($announcement->metric?->fresh()?->total_click_count)->toBe(2);
    }

    public function test_the_public_click_route_uses_the_frozen_url_and_rejects_non_exact_tokens(): void
    {
        [, $announcement, , $delivery] = $this->deliveredNotification();
        $announcement->updateQuietly(['destination_url' => 'https://offers.example.com/frozen-destination']);

        $this->get(route('partner-announcements.click', $delivery->click_token).'?destination=https://attacker.example')
            ->assertRedirect('https://offers.example.com/frozen-destination');
        $this->get(route('partner-announcements.click', strtoupper($delivery->click_token)))
            ->assertNotFound();

        expect($announcement->metric?->fresh()?->total_click_count)->toBe(1);
    }

    public function test_two_concurrent_first_clicks_increment_unique_once_and_total_twice_on_mysql(): void
    {
        $this->requirePcntl();
        [, $announcement, , $delivery] = $this->deliveredNotification();
        $firstControl = $this->socketPair();
        $secondControl = $this->socketPair();
        $firstResultFile = $this->resultFile('partner-click-first-');
        $secondResultFile = $this->resultFile('partner-click-second-');

        DB::disconnect();
        $firstPid = pcntl_fork();
        $this->assertNotSame(-1, $firstPid);

        if ($firstPid === 0) {
            fclose($firstControl[0]);
            fclose($secondControl[0]);
            fclose($secondControl[1]);
            DB::purge();

            try {
                PartnerAnnouncementDelivery::updating(static function (PartnerAnnouncementDelivery $candidate) use ($delivery, $firstControl): void {
                    if ($candidate->id !== $delivery->id || $candidate->click_count !== 1) {
                        return;
                    }

                    fwrite($firstControl[1], 'L');
                    if (fread($firstControl[1], 1) !== 'G') {
                        throw new RuntimeException('The first click transaction was not released.');
                    }
                });
                $url = app(RecordPartnerAnnouncementClick::class)->handle($delivery->click_token);
                file_put_contents($firstResultFile, json_encode(['url' => $url], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($firstResultFile, json_encode(['error' => $exception::class.': '.$exception->getMessage()], JSON_THROW_ON_ERROR));
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
                $url = app(RecordPartnerAnnouncementClick::class)->handle($delivery->click_token);
                file_put_contents($secondResultFile, json_encode(['url' => $url], JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($secondResultFile, json_encode(['error' => $exception::class.': '.$exception->getMessage()], JSON_THROW_ON_ERROR));
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

        expect([pcntl_wexitstatus($firstStatus), pcntl_wexitstatus($secondStatus)])->toBe([0, 0])
            ->and($this->readResult($firstResultFile))->toBe(['url' => 'https://offers.example.com/frozen-destination'])
            ->and($this->readResult($secondResultFile))->toBe(['url' => 'https://offers.example.com/frozen-destination'])
            ->and($delivery->fresh()?->click_count)->toBe(2)
            ->and($announcement->metric?->fresh()?->unique_click_count)->toBe(1)
            ->and($announcement->metric?->fresh()?->total_click_count)->toBe(2);
    }

    /** @return array{User, PartnerAnnouncement, DatabaseNotification, PartnerAnnouncementDelivery} */
    private function deliveredNotification(?User $member = null): array
    {
        $member ??= User::factory()->withProfile()->create();
        $announcement = PartnerAnnouncement::factory()->create([
            'destination_url' => 'https://offers.example.com/frozen-destination',
        ]);
        PartnerAnnouncementMetric::query()->create(['partner_announcement_id' => $announcement->id]);
        $notification = $member->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'partner-announcement',
            'data' => [
                'category' => 'partners',
                'translation_key' => 'notifications.items.partner_announcement',
                'parameters' => ['announcement' => $announcement->title],
                'target_type' => 'partner_announcement',
                'target_id' => $announcement->id,
            ],
        ]);
        $delivery = PartnerAnnouncementDelivery::factory()
            ->for($announcement, 'announcement')
            ->for($member)
            ->create([
                'notification_id' => $notification->id,
                'status' => PartnerDeliveryStatus::Delivered,
                'delivered_at' => now(),
            ]);

        return [$member, $announcement, $notification, $delivery];
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

    /** @return array<string, string> */
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
