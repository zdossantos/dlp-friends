<?php

namespace App\Actions\Fortify;

use App\Concerns\AccountValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use AccountValidationRules, PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'email' => $this->emailRules(),
            'birth_date' => [
                'required',
                Rule::date()->beforeOrEqual(today()->subYears(18)),
            ],
            'password' => $this->passwordRules(),
            'terms_accepted' => ['required', 'accepted'],
        ], [
            'birth_date.before_or_equal' => __('account.registration.adult_only'),
            'terms_accepted.accepted' => __('account.registration.terms_required'),
            'terms_accepted.required' => __('account.registration.terms_required'),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::query()->create([
                'email' => $input['email'],
                'birth_date' => $input['birth_date'],
                'password' => $input['password'],
            ]);

            $role = Role::query()->where('name', RoleName::User)->firstOrFail();
            $user->roles()->attach($role);

            $user->termsAcceptances()->create([
                'terms_version' => config('legal.terms.version'),
                'accepted_at' => now(),
            ]);

            return $user;
        });
    }
}
