<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateMemberRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof User
            && Gate::forUser($this->user())->allows('manageRoles', $member);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'distinct', Rule::enum(RoleName::class)],
            'confirmed' => ['required', 'accepted'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $roles = $this->input('roles');

            if (is_array($roles) && in_array(RoleName::Admin->value, $roles, true)) {
                $validator->errors()->add('roles', __('administration.members.roles_admin_read_only'));
            }
        }];
    }

    /** @return list<string> */
    public function roles(): array
    {
        $roles = $this->validated('roles', []);

        return is_array($roles) ? array_values($roles) : [];
    }
}
