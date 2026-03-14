<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRule
{
    public static function defaults(): Password
    {
        $rule = Password::min(max(8, (int) config('security.password.min_length', 10)));

        if ((bool) config('security.password.require_letters', true)) {
            $rule->letters();
        }

        if ((bool) config('security.password.require_numbers', true)) {
            $rule->numbers();
        }

        if ((bool) config('security.password.require_symbols', false)) {
            $rule->symbols();
        }

        if ((bool) config('security.password.require_mixed_case', false)) {
            $rule->mixedCase();
        }

        return $rule;
    }
}
