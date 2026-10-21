<?php

namespace App\Http\Requests;

use App\Models\TripRegistration;
use Illuminate\Foundation\Http\FormRequest;

class RemoveTripParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $registration = $this->route('registration');

        return $registration instanceof TripRegistration && ($this->user()?->can('manage', $registration->trip) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000'], 'confirmed' => ['required', 'accepted']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('trips.attributes');
    }
}
