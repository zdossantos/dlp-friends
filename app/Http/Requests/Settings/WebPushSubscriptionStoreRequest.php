<?php

namespace App\Http\Requests\Settings;

use App\Support\WebPushEndpoint;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class WebPushSubscriptionStoreRequest extends FormRequest
{
    /** @return array<string, list<string|Closure>> */
    public function rules(): array
    {
        return [
            'endpoint' => [
                'required',
                'url:https',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! WebPushEndpoint::isAllowed($value)) {
                        $fail(__('account.settings.notifications.invalid_endpoint'));
                    }
                },
            ],
            'keys' => ['required', 'array:auth,p256dh'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['sometimes', 'in:aes128gcm,aesgcm'],
            'device_name' => ['nullable', 'string', 'max:80'],
            'platform' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array{
     *     endpoint: string,
     *     keys: array{p256dh: string, auth: string},
     *     content_encoding?: string,
     *     device_name?: string|null,
     *     platform?: string|null
     * }
     */
    public function subscription(): array
    {
        return [
            'endpoint' => $this->string('endpoint')->toString(),
            'keys' => [
                'p256dh' => $this->string('keys.p256dh')->toString(),
                'auth' => $this->string('keys.auth')->toString(),
            ],
            'content_encoding' => $this->string('content_encoding', 'aes128gcm')->toString(),
            'device_name' => $this->filled('device_name') ? $this->string('device_name')->toString() : null,
            'platform' => $this->filled('platform') ? $this->string('platform')->toString() : null,
        ];
    }
}
