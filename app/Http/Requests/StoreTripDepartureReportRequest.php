<?php

namespace App\Http\Requests;

use App\Models\TripDeparture;
use App\Models\TripDepartureReport;
use Illuminate\Foundation\Http\FormRequest;

final class StoreTripDepartureReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $departure = $this->route('departure');

        return $departure instanceof TripDeparture && ($this->user()?->can('create', [TripDepartureReport::class, $departure]) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['details' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/u'], 'confirmed' => ['required', 'accepted']];
    }
}
