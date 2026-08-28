<?php

namespace App\Actions\Fortify;

use App\Actions\Premium\StartPremiumCheckout;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
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
        $wantPremium = (bool) ($input['want_premium'] ?? false);

        $rules = [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ];

        if ($wantPremium) {
            $rules['plan'] = ['required', 'string', 'in:'.implode(',', array_keys(StartPremiumCheckout::PLAN_PRICE_CONFIG_KEYS))];
            $rules['terms_agree'] = ['accepted'];
        }

        Validator::make($input, $rules)->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        if ($wantPremium) {
            session(['premium_registration_plan' => $input['plan']]);
        }

        return $user;
    }
}
