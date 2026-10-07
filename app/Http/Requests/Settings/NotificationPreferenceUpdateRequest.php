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
            'required_without_all:messages,matches,events,administration,admin_new_member_alerts,weekly_recap_messages,weekly_recap_matches',
            'boolean',
        ];
        $rules['weekly_recap_messages'] = ['sometimes', 'boolean'];
        $rules['weekly_recap_matches'] = ['sometimes', 'boolean'];
        $rules['admin_new_member_alerts'] = ['sometimes', 'boolean'];

        return $rules;
    }
}
