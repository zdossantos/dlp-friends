<?php

namespace App\Http\Requests\Admin;

use App\Models\SeasonalTheme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSeasonalThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seasonalTheme = $this->route('seasonalTheme');

        return $seasonalTheme instanceof SeasonalTheme
            && ($this->user()?->can('update', $seasonalTheme) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'required_with:ends_at'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'required_with:starts_at', 'after:starts_at'],
        ];
    }
}
