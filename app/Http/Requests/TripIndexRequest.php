<?php

namespace App\Http\Requests;

use App\Enums\TripTransport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TripIndexRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['transport' => ['nullable', Rule::enum(TripTransport::class)], 'search' => ['nullable', 'string', 'max:160']];
    }
}
