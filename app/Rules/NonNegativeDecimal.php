<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NonNegativeDecimal implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || (float) $value < 0) {
            $fail('The :attribute must be zero or a positive number.');
        }
    }
}
