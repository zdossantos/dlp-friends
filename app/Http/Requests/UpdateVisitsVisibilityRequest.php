<?php

namespace App\Http\Requests;

use App\Enums\VisitsVisibility;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitsVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Visit::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['visibility' => ['required', Rule::enum(VisitsVisibility::class)], 'confirmed' => ['sometimes', 'boolean']];
    }
}
