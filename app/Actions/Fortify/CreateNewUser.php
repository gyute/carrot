<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
        $input['username'] = Str::lower(trim($input['username'] ?? ''));

        Validator::make($input, [
            ...$this->profileRules(),
            'username' => $this->usernameRules(),
            'password' => $this->passwordRules(),
        ], [
            'username.regex' => 'ログインIDは半角英小文字ではじまり、半角英小文字・数字・-・_ のみ使用できます。',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
