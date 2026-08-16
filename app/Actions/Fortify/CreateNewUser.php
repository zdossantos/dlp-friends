<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['username'] = $this->normalizeUsername($input['username'] ?? null);

        Validator::make($input, [
            ...$this->profileRules(),
            'birth_date' => [
                'required',
                Rule::date()->beforeOrEqual(today()->subYears(18)),
            ],
            'password' => $this->passwordRules(),
        ], [
            'birth_date.before_or_equal' => 'Vous devez être majeur pour vous inscrire.',
        ])->validate();

        return User::create([
            'username' => $input['username'],
            'email' => $input['email'],
            'birth_date' => $input['birth_date'],
            'password' => $input['password'],
        ]);
    }
}
