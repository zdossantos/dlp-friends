<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EventChatIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && Gate::forUser($this->user())->allows('view', $event->chat);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'messages' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
