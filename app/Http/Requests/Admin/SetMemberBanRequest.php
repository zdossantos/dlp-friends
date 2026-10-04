<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class SetMemberBanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ban', $this->route('member')) ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['banned' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/u'], 'confirmed' => ['required', 'accepted']];
    }
}
