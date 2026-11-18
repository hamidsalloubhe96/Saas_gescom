<?php

namespace App\Rules;

use App\Support\DisposableEmails;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotDisposableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && DisposableEmails::is($value)) {
            $fail('Les adresses e-mail temporaires ne sont pas acceptées. Utilisez votre adresse professionnelle.');
        }
    }
}
