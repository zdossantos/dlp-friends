<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class DiscoveryPreferenceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateDiscoveryPreferences', $this->user()) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'min_age' => ['required', 'integer', 'between:18,120'],
            'max_age' => ['required', 'integer', 'between:18,120', 'gte:min_age'],
            'min_common_interests' => ['required', 'integer', 'between:0,100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'min_age' => __('account.settings.discovery.min_age'),
            'max_age' => __('account.settings.discovery.max_age'),
            'min_common_interests' => __('account.settings.discovery.min_common_interests'),
        ];
    }
}
