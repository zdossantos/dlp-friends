<?php

namespace App\Http\Requests;

use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $displayName = $this->input('display_name');

        $this->merge([
            'display_name' => is_string($displayName)
                ? preg_replace('/\s+/u', ' ', trim($displayName))
                : $displayName,
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'min:1', 'max:80'],
            'bio' => ['nullable', 'string', 'max:500'],
            'visit_frequency' => ['required', Rule::enum(VisitFrequency::class)],
            'visibility' => ['required', Rule::enum(ProfileVisibility::class)],
        ];
    }
}
