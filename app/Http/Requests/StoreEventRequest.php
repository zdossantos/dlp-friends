<?php

namespace App\Http\Requests;

use App\Enums\EventRegistrationMode;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'general_location' => ['required', 'string', 'max:160'],
            'detailed_location' => ['required', 'string', 'max:255'],
            'starts_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $startsAt = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, 'Europe/Paris');

                    if ($startsAt->isPast()) {
                        $fail('validation.after')->translate(['date' => 'now']);
                    }
                },
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'registration_mode' => ['required', Rule::enum(EventRegistrationMode::class)],
        ];
    }
}
