<?php

namespace App\Http\Requests;

use App\Enums\ProfileVisibility;
use App\Enums\VisitFrequency;
use App\Models\InterestSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MemberProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $displayName = $this->input('display_name');

        $this->merge([
            'display_name' => is_string($displayName)
                ? preg_replace('/\s+/u', ' ', trim($displayName))
                : $displayName,
            'interest_ids' => $this->input('interest_ids', []),
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'min:1', 'max:80'],
            'bio' => ['nullable', 'string', 'max:500'],
            'visit_frequency' => ['required', Rule::enum(VisitFrequency::class)],
            'visibility' => ['required', Rule::enum(ProfileVisibility::class)],
            'interest_ids' => ['present', 'array'],
            'interest_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('interests', 'id')->where('is_active', true),
            ],
        ];
    }

    /** @return list<int> */
    public function interestIds(): array
    {
        $interestIds = $this->validated('interest_ids', []);

        if (! is_array($interestIds)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $interestId): int => (int) $interestId,
            $interestIds,
        ));
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $interestIds = $this->input('interest_ids', []);

            if (! is_array($interestIds)) {
                return;
            }

            $submitted = collect($interestIds)
                ->map(fn (mixed $id): int => (int) $id);
            $current = $this->user()?->profile?->interests()
                ->pluck('interests.id') ?? collect();
            $limit = InterestSetting::current()->max_selections;

            if ($submitted->count() > $limit && $submitted->diff($current)->isNotEmpty()) {
                $validator->errors()->add(
                    'interest_ids',
                    "Vous pouvez sélectionner au maximum {$limit} intérêts.",
                );
            }
        }];
    }
}
