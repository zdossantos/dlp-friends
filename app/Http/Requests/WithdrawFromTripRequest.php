<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawFromTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:1000']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('trips.attributes');
    }
}
