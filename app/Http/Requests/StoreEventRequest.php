<?php

namespace App\Http\Requests;

use App\Enums\EventRegistrationMode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'starts_at' => ['bail', 'required', 'date_format:Y-m-d\TH:i'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'registration_mode' => ['required', Rule::enum(EventRegistrationMode::class)],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('starts_at')) {
                return;
            }

            $raw = $this->string('starts_at')->toString();
            $startsAt = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $raw, 'Europe/Paris');

            if ($startsAt === null || $startsAt->format('Y-m-d\TH:i') !== $raw) {
                $validator->errors()->add('starts_at', __('events.validation.local_time'));
            } elseif ($startsAt->lte(CarbonImmutable::now())) {
                $validator->errors()->add('starts_at', __('events.validation.starts_at_future'));
            }
        }];
    }
}
