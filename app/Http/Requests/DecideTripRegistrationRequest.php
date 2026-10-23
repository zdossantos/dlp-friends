<?php

namespace App\Http\Requests;

use App\Models\TripRegistration;
use Illuminate\Foundation\Http\FormRequest;

class DecideTripRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $registration = $this->route('registration');

        return $registration instanceof TripRegistration && ($this->user()?->can('manage', $registration->trip) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['accept' => ['required', 'boolean'], 'reason' => ['required_if:accept,false', 'nullable', 'string', 'max:1000']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('trips.attributes');
    }
}
