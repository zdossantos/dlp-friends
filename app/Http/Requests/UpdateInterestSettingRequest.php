<?php

namespace App\Http\Requests;

use App\Models\Interest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInterestSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Interest::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'max_selections' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
