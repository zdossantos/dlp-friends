<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;

class TripPlaceSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Trip::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['query' => ['required', 'string', 'min:3', 'max:160']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['query' => __('trips.place_search.query')];
    }
}
