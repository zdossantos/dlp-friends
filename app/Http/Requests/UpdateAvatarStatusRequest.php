<?php

namespace App\Http\Requests;

use App\Models\Avatar;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarStatusRequest extends FormRequest
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
        return ['is_active' => ['required', 'boolean']];
    }
}
