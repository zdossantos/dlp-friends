<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;

final class OrderPartnerProfilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Admin) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['present', 'array'],
            'ordered_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /** @return list<int> */
    public function orderedIds(): array
    {
        $ids = $this->validated('ordered_ids', []);

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $ids));
    }
}
