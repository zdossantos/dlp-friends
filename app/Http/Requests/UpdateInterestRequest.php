<?php

namespace App\Http\Requests;

use App\Models\Interest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $interest = $this->route('interest');

        return $interest instanceof Interest
            && ($this->user()?->can('update', $interest) ?? false);
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
                Rule::unique('interests', 'name')->ignore($this->route('interest')),
            ],
        ];
    }
}
