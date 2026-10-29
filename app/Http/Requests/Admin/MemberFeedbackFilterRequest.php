<?php

namespace App\Http\Requests\Admin;

use App\Enums\MemberFeedbackArea;
use App\Enums\MemberFeedbackKind;
use App\Enums\MemberFeedbackStatus;
use App\Models\MemberFeedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberFeedbackFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MemberFeedback::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::enum(MemberFeedbackKind::class)],
            'area' => ['nullable', Rule::enum(MemberFeedbackArea::class)],
            'status' => ['nullable', Rule::enum(MemberFeedbackStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
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
