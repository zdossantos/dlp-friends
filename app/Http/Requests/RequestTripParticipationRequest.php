<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestTripParticipationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('request', $this->route('trip')) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('trips.attributes');
    }
}
