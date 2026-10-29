<?php

namespace App\Http\Requests\Admin;

use App\Enums\MemberFeedbackStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('feedback')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(MemberFeedbackStatus::class)],
            'internal_note' => ['present', 'nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'kind' => __('feedback.kind'), 'area' => __('feedback.area'),
            'content' => __('feedback.content'), 'status' => __('feedback.status'),
            'internal_note' => __('feedback.internal_note'),
        ];
    }
}
