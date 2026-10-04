<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CloseConversationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('close', $this->route('report')) ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['decision' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/u'], 'confirmed' => ['required', 'accepted']];
    }
}
