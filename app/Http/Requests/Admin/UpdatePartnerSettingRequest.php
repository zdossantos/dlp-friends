<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePartnerSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Admin) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'cooldown_days' => ['required', 'integer', 'between:1,365'],
        ];
    }

    public function cooldownDays(): int
    {
        return $this->integer('cooldown_days');
    }
}
