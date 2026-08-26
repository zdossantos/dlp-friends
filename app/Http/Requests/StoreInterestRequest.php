<?php

namespace App\Http\Requests;

use App\Models\Interest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
                ? Str::squish($name)
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
            'name_en' => ['nullable', 'string', 'max:80'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => __('Le nom a déjà été utilisé.'),
        ];
    }
}
