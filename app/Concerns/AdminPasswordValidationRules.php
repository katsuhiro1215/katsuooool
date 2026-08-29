<?php

namespace App\Concerns;

use Illuminate\Validation\Rules\Password;

trait AdminPasswordValidationRules
{
    /**
     * @return array<int, Password|string>
     */
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised(),
            'confirmed',
        ];
    }
}
