<?php

namespace App\Jobs;

use App\Actions\BuildWeeklyEmailRecap;
use App\Mail\WeeklyEmailRecapMail;
use App\Models\User;
use App\Models\WeeklyEmailRecapDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Throwable;

class SendWeeklyEmailRecap implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $deliveryId) {}

    public function failed(?Throwable $exception): void
    {
        WeeklyEmailRecapDelivery::query()->whereKey($this->deliveryId)
            ->whereNull('sent_at')->whereNull('skipped_at')->whereNull('reserved_at')
            ->update(['skipped_at' => now(), 'reserved_at' => null, 'resend_payload' => null]);
    }

    public function handle(BuildWeeklyEmailRecap $build): void
    {
        $mail = null;
        $recipient = null;
        $delivery = DB::transaction(function () use ($build, &$mail, &$recipient): ?WeeklyEmailRecapDelivery {
            $delivery = WeeklyEmailRecapDelivery::query()->lockForUpdate()->find($this->deliveryId);
            if ($delivery === null || $delivery->sent_at !== null || $delivery->skipped_at !== null) {
                return null;
            }
            if ($delivery->reserved_at?->isAfter(now()->subMinutes(10))) {
                $this->release(max(1, (int) now()->diffInSeconds($delivery->reserved_at->addMinutes(10), false) + 1));

                return null;
            }

            $user = User::query()->find($delivery->user_id);
            $expiresAt = $delivery->period_ends_at->setTimezone('Europe/Paris')->addWeek();
            $recap = $user !== null && CarbonImmutable::now()->isBefore($expiresAt)
                ? $build->handle($user, $delivery->period_ends_at) : null;
            $snapshot = $delivery->resend_payload;
            // Resend retains keys for 24 hours; stop before a retry could become a new send.
            $uncertain = $delivery->first_attempt_at?->isBefore(now()->subHours(23)) ?? false;
            if ($recap === null || $uncertain || ($snapshot !== null && (
                config('mail.default') !== 'resend' || $snapshot['request']['to'] !== [$user->email]
                || ($snapshot['matches_enabled'] && ! $user->weekly_recap_matches)
            ))) {
                $delivery->update(['skipped_at' => now(), 'reserved_at' => null, 'resend_payload' => null]);
                if ($uncertain) {
                    Log::warning('Weekly recap acceptance remains uncertain; automatic resend stopped.', ['delivery_id' => $delivery->id]);
                }

                return null;
            }

            $recipient = $user->email;
            $mail = (new WeeklyEmailRecapMail($recap['conversations'], $recap['matches']))->locale($user->preferredLocale());
            if (config('mail.default') === 'resend' && $snapshot === null) {
                $request = $mail->withLocale($user->preferredLocale(), fn () => [
                    'from' => (new Address(config('mail.from.address'), config('mail.from.name')))->toString(),
                    'to' => [$recipient],
                    'subject' => __('weekly-recap.subject'),
                    'html' => $mail->render(),
                ]);
                $delivery->fill([
                    'idempotency_key' => (string) Str::uuid(),
                    'first_attempt_at' => now(),
                    'resend_payload' => ['request' => $request, 'matches_enabled' => $recap['matches'] !== null],
                ]);
            }
            $delivery->fill(['reserved_at' => now()])->save();

            return $delivery;
        });

        if ($delivery === null) {
            return;
        }

        try {
            if ($delivery->resend_payload !== null) {
                // Laravel's Resend transport does not pass the HTTP idempotency option.
                $response = Http::withToken(config('services.resend.key'))
                    ->withHeaders(['Idempotency-Key' => $delivery->idempotency_key])
                    ->connectTimeout(5)->timeout(30)
                    ->post('https://api.resend.com/emails', $delivery->resend_payload['request']);
                if (! $response->successful() || ! is_string($response->json('id'))) {
                    throw new RuntimeException('Resend weekly recap request failed.');
                }
            } else {
                Mail::to($recipient)->send($mail);
            }

            $delivery->update(['sent_at' => now(), 'reserved_at' => null, 'resend_payload' => null]);
        } catch (Throwable $exception) {
            $delivery->update(['reserved_at' => null]);
            throw $exception;
        }
    }
}
