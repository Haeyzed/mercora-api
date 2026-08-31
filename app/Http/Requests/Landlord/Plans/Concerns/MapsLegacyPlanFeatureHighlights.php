<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plans\Concerns;

/**
 * Maps legacy plan marketing feature payloads to the canonical column name.
 */
trait MapsLegacyPlanFeatureHighlights
{
    protected function prepareForValidation(): void
    {
        if ($this->has('features') && ! $this->has('feature_highlights')) {
            $this->merge([
                'feature_highlights' => $this->input('features'),
            ]);
        }
    }
}
