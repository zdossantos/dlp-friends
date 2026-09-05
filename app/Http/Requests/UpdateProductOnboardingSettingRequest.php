<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductOnboardingSettingRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'pass_avatar_id' => [
                'required',
                'integer',
                Rule::exists('avatars', 'id')->where('is_active', true),
            ],
            'like_avatar_id' => [
                'required',
                'integer',
                'different:pass_avatar_id',
                Rule::exists('avatars', 'id')->where('is_active', true),
            ],
            'pass_display_name' => ['required', 'string', 'max:80'],
            'pass_display_name_en' => ['nullable', 'string', 'max:80'],
            'pass_bio' => ['required', 'string', 'max:500'],
            'pass_bio_en' => ['nullable', 'string', 'max:500'],
            'like_display_name' => ['required', 'string', 'max:80'],
            'like_display_name_en' => ['nullable', 'string', 'max:80'],
            'like_bio' => ['required', 'string', 'max:500'],
            'like_bio_en' => ['nullable', 'string', 'max:500'],
        ];
    }
}
