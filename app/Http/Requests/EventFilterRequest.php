<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventFilterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return $this->query();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->query('from') !== null && $this->query('from') !== '', ['after_or_equal:from'])],
            'search' => ['nullable', 'string', 'max:160'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['from' => __('events.filters.from'), 'to' => __('events.filters.to'), 'search' => __('events.filters.search')];
    }

    /** @return array<string, string> */
    public function filters(): array
    {
        return array_filter(array_map(fn (?string $value): string => trim($value ?? ''), $this->validated()), fn (string $value): bool => $value !== '');
    }
}
