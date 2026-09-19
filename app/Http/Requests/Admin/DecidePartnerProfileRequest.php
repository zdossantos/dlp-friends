<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DecidePartnerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Admin) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function decision(): string
    {
        $decision = $this->validated('decision');

        return is_string($decision) ? $decision : '';
    }

    public function rejectionReason(): ?string
    {
        $reason = $this->validated('rejection_reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }
}
