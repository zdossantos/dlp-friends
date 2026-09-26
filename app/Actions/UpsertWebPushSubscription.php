<?php

namespace App\Actions;

use App\Enums\WebPushPreference;
use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertWebPushSubscription
{
    /**
     * @param  array{
     *     endpoint: string,
     *     keys: array{p256dh: string, auth: string},
     *     content_encoding?: string,
     *     device_name?: string|null,
     *     platform?: string|null
     * }  $data
     * @return array{WebPushSubscription, bool}
     */
    public function handle(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $hash = hash('sha256', $data['endpoint']);
            $existing = WebPushSubscription::query()->where('endpoint_hash', $hash)->lockForUpdate()->first();

            if ($existing !== null && $existing->user_id !== $user->id && $existing->revoked_at === null) {
                throw ValidationException::withMessages(['endpoint' => __('account.settings.notifications.endpoint_in_use')]);
            }

            $created = $existing === null || $existing->revoked_at !== null;
            $subscription = $existing ?? new WebPushSubscription;
            $subscription->fill([
                'user_id' => $user->id, 'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'device_name' => $data['device_name'] ?? null, 'platform' => $data['platform'] ?? null,
                'last_used_at' => now(), 'revoked_at' => null,
            ])->save();

            foreach (WebPushPreference::cases() as $preference) {
                $user->notificationPreferences()->firstOrCreate(['category' => $preference], ['enabled' => true]);
            }

            return [$subscription->refresh(), $created];
        });
    }
}
