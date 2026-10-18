<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

final class CheckEmailRegistrationAvailability
{
    public function handle(bool $fresh = false): bool
    {
        if (config('mail.mailers.'.config('mail.default').'.transport') !== 'resend') {
            return true;
        }

        $key = config('services.resend.usage_key');
        if (! is_string($key) || trim($key) === '') {
            return false;
        }

        $cacheKey = 'email-registration:resend:'.hash('sha256', $key);
        if (! $fresh && is_bool($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $expiresAt = CarbonImmutable::now()->addSeconds(60);
        $available = false;

        try {
            $response = Http::withToken($key)->acceptJson()
                ->connectTimeout(2)->timeout(5)->get('https://api.resend.com/usage');

            $usage = $response->json();
            if ($response->successful() && is_array($usage)) {
                $available = true;
                foreach (['daily', 'monthly'] as $period) {
                    $quota = data_get($usage, 'emails.'.$period);
                    if (! is_array($quota) || Validator::make($quota, [
                        'used' => ['required', 'integer', 'min:0'],
                        'limit' => ['present', 'nullable', 'integer', 'min:0'],
                        'resets_at' => ['required_unless:limit,null', 'nullable', 'date', 'after:now'],
                    ])->fails()) {
                        $available = false;
                        break;
                    }

                    if ($quota['limit'] !== null) {
                        $expiresAt = $expiresAt->min(CarbonImmutable::parse($quota['resets_at']));
                        if ($quota['used'] >= $quota['limit']) {
                            $available = false;
                        }
                    }
                }
            }
        } catch (ConnectionException) {
            // Fail closed without logging credentials or the provider response.
        }

        Cache::put($cacheKey, $available, $expiresAt);

        return $available;
    }
}
