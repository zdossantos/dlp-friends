<?php

namespace App\Http\Requests;

use App\Enums\TripAudience;
use App\Enums\TripDirection;
use App\Enums\TripTransport;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Trip::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'transport' => ['required', Rule::enum(TripTransport::class)],
            'direction' => ['required', Rule::enum(TripDirection::class)],
            'general_departure' => ['required', 'string', 'max:160'],
            'destination' => ['required', 'string', 'max:160'],
            'departs_at' => ['bail', 'required', 'date_format:Y-m-d\TH:i'],
            'meeting_point' => ['required', 'string', 'max:255'],
            'audience' => ['sometimes', 'required', Rule::enum(TripAudience::class)],
            'car_seats' => ['nullable', 'required_if:transport,car', 'prohibited_unless:transport,car', 'integer', 'min:1', 'max:65535'],
            'confirm_members' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $raw = $this->string('departs_at')->toString();
            $departure = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $raw, 'Europe/Paris');
            if ($departure === null || $departure->format('Y-m-d\TH:i') !== $raw) {
                $validator->errors()->add('departs_at', __('trips.validation.local_time'));
            } elseif ($departure->lte(CarbonImmutable::now())) {
                $validator->errors()->add('departs_at', __('trips.validation.future'));
            } elseif ($departure->startOfDay()->gt(CarbonImmutable::today('Europe/Paris')->addMonthsNoOverflow(12))) {
                $validator->errors()->add('departs_at', __('trips.validation.horizon'));
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('trips.attributes');
    }
}
