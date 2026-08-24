<?php

namespace App\Http\Requests;

use App\Models\Interest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Interest::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        $this->merge([
            'name' => is_string($name)
                ? preg_replace('/\s+/u', ' ', trim($name))
                : $name,
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
                Rule::unique('interests', 'name'),
            ],
        ];
    }
}
