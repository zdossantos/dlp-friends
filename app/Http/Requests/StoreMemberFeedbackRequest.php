<?php

namespace App\Http\Requests;

use App\Enums\MemberFeedbackArea;
use App\Enums\MemberFeedbackKind;
use App\Models\MemberFeedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MemberFeedback::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(MemberFeedbackKind::class)],
            'area' => ['required', Rule::enum(MemberFeedbackArea::class)],
            'content' => ['required', 'string', 'max:5000', 'not_regex:/^\\s*$/u'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['content.not_regex' => __('feedback.required')];
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
