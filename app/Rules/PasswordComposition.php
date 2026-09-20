<?php
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordComposition implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) { $fail('The password must be a string.'); return; }
        if (preg_match_all('/\p{L}/u', $value) < 4) $fail('The password must contain at least 4 letters.');
        if (!preg_match('/\p{Lu}/u', $value) || !preg_match('/\p{Ll}/u', $value)) $fail('The password must include an uppercase and a lowercase letter.');
        if (!preg_match('/\p{N}/u', $value)) $fail('The password must include at least one number.');
        if (!preg_match('/[\p{P}\p{S}]/u', $value)) $fail('The password must include a special character, such as @, !, or #.');
    }
}
