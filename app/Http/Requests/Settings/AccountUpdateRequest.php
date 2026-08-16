<?php

namespace App\Http\Requests\Settings;

use App\Concerns\AccountValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountUpdateRequest extends FormRequest
{
    use AccountValidationRules;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'email' => $this->emailRules($this->user()->id),
        ];
    }
}
