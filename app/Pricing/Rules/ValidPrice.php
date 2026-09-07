<?php

declare(strict_types=1);

namespace App\Pricing\Rules;

use App\Pricing\Price;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * Accepts exactly what the Price value object accepts, so nothing that passes
 * validation can blow up when it is parsed.
 */
final class ValidPrice implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value)) {
            $fail('The :attribute must be a positive price.');

            return;
        }

        try {
            Price::fromDecimal((string) $value);
        } catch (InvalidArgumentException) {
            $fail('The :attribute must be a positive price with up to four decimal places.');
        }
    }
}
