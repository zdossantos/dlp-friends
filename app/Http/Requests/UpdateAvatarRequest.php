<?php

namespace App\Http\Requests;

use App\Models\Avatar;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $avatar = $this->route('avatar');

        return $avatar instanceof Avatar
            && ($this->user()?->can('update', $avatar) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $primaryColor = $this->input('primary_color');
        $secondaryColor = $this->input('secondary_color');

        $this->merge([
            'name' => is_string($name) ? Str::squish($name) : $name,
            'primary_color' => is_string($primaryColor) ? strtoupper($primaryColor) : $primaryColor,
            'secondary_color' => is_string($secondaryColor) ? strtoupper($secondaryColor) : $secondaryColor,
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('avatars', 'name')->ignore($this->route('avatar')),
            ],
            'image' => ['nullable', File::image()->types(['png', 'webp'])->max(2048)->dimensions(Rule::dimensions()->maxWidth(1200)->maxHeight(1200))],
            'primary_color' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
        ];
    }
}
