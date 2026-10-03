<?php

namespace App\Http\Requests;

use App\Enums\ProfileVisibility;
use App\Enums\SocialLinksVisibility;
use App\Enums\SocialNetwork;
use App\Enums\VisitFrequency;
use App\Models\InterestSetting;
use App\Rules\SocialNetworkUrl;
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
            'avatar_id' => $this->input('avatar_id', $this->user()?->profile?->avatar_id),
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
            'avatar_id' => [
                'required',
                'integer',
                Rule::exists('avatars', 'id')->where('is_active', true),
            ],
            'display_name' => ['required', 'string', 'min:1', 'max:80'],
            'bio' => ['nullable', 'string', 'max:500'],
            'visit_frequency' => ['required', Rule::enum(VisitFrequency::class)],
            'visibility' => ['required', Rule::enum(ProfileVisibility::class)],
            'social_links_visibility' => ['sometimes', 'required', Rule::enum(SocialLinksVisibility::class)],
            'social_links' => ['sometimes', 'array', 'list', 'max:3'],
            'social_links.*' => ['required', 'array:network,url'],
            'social_links.*.network' => ['required', 'string', 'distinct', Rule::enum(SocialNetwork::class)],
            'social_links.*.url' => ['required', 'string', 'max:2048', new SocialNetworkUrl],
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
                    trans_choice('profile.interest_limit', $limit, ['max' => $limit]),
                );
            }
        }];
    }
}
