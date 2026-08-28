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
        ];
    }
}
