<?php

namespace App\Http\Requests;

use App\Enums\ProductOnboardingStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'step' => ['required', Rule::enum(ProductOnboardingStep::class)],
        ];
    }
}
