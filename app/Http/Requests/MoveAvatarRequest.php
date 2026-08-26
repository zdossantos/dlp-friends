<?php

namespace App\Http\Requests;

use App\Models\Avatar;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveAvatarRequest extends FormRequest
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
        return ['direction' => ['required', Rule::in(['up', 'down'])]];
    }
}
