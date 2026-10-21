<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BlockMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['confirm_trip_departures' => ['sometimes', 'boolean'], 'return_to' => ['nullable', 'string', 'max:2048']];
    }
}
