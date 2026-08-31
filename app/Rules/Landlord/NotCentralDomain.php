<?php

declare(strict_types=1);

namespace App\Rules\Landlord;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class NotCentralDomain implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower((string) $value);

        if (in_array($domain, config('tenancy.central_domains'), true)) {
            $fail('The :attribute cannot be a central domain.');
        }
    }
}
