<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TripChatIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip instanceof Trip
            && Gate::forUser($this->user())->allows('view', $trip->chat);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'messages' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
