<?php

namespace App\Http\Requests;

use App\Enums\ConversationReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('conversation')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['reason' => ['required', Rule::enum(ConversationReportReason::class)], 'details' => ['nullable', 'string', 'max:1000'], 'block' => ['required', 'boolean'], 'confirmed' => ['required', 'accepted']];
    }
}
