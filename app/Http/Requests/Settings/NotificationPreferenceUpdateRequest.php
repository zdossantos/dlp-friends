<?php

namespace App\Http\Requests\Settings;

use App\Enums\WebPushPreference;
use Illuminate\Foundation\Http\FormRequest;

class NotificationPreferenceUpdateRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = collect(WebPushPreference::cases())
            ->mapWithKeys(fn (WebPushPreference $preference): array => [$preference->value => ['sometimes', 'boolean']])
            ->all();
        $rules[WebPushPreference::PartnerAnnouncements->value] = [
            'required_without_all:messages,matches,events,administration,admin_new_member_alerts',
            'boolean',
        ];
        $rules['admin_new_member_alerts'] = ['sometimes', 'boolean'];

        return $rules;
    }
}
