<?php

namespace App\Http\Requests;

use App\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(Locale::SUPPORTED)],
        ];
    }
}
