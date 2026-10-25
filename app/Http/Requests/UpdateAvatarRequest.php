<?php

namespace App\Http\Requests;

use App\Models\Avatar;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateAvatarRequest extends StoreAvatarRequest
{
    public function authorize(): bool
    {
        $avatar = $this->route('avatar');

        return $avatar instanceof Avatar
            && ($this->user()?->can('update', $avatar) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('avatars', 'name')->ignore($this->route('avatar')),
            ],
            'image' => ['nullable', File::image()->types(['png', 'webp'])->max(2048)->dimensions(Rule::dimensions()->maxWidth(1200)->maxHeight(1200))],
        ];
    }
}
