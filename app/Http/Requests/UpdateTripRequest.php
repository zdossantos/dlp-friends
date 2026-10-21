<?php

namespace App\Http\Requests;

use App\Models\Trip;

class UpdateTripRequest extends StoreTripRequest
{
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip instanceof Trip && ($this->user()?->can('manage', $trip) ?? false);
    }
}
