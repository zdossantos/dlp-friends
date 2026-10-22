<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTripChatReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip instanceof Trip
            && Gate::forUser($this->user())->allows('view', $trip->chat);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        /** @var Trip $trip */
        $trip = $this->route('trip');

        return [
            'last_read_message_id' => [
                'required',
                'integer',
                Rule::exists('group_chat_messages', 'id')
                    ->where('group_chat_id', $trip->chat->id),
            ],
        ];
    }
}
