<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreEventChatReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && Gate::forUser($this->user())->allows('view', $event->chat);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');

        return [
            'last_read_message_id' => [
                'required',
                'integer',
                Rule::exists('event_chat_messages', 'id')
                    ->where('event_chat_id', $event->chat->id),
            ],
        ];
    }
}
