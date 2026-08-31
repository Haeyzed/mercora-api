<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait AllowsIncludes
{
    /**
     * @return list<string>
     */
    abstract protected function allowedIncludes(): array;

    /**
     * @return list<string>
     */
    protected function parsedIncludes(mixed $include): array
    {
        if (is_array($include)) {
            $requested = $include;
        } elseif (is_string($include) && $include !== '') {
            $requested = array_map(trim(...), explode(',', $include));
        } else {
            return [];
        }

        return array_values(array_intersect(
            array_values(array_filter($requested, fn (mixed $value): bool => is_string($value) && $value !== '')),
            $this->allowedIncludes(),
        ));
    }

    #[Scope]
    protected function withIncludes(Builder $query, mixed $include): void
    {
        $includes = $this->parsedIncludes($include);

        if ($includes !== []) {
            $query->with($includes);
        }
    }

    public function loadAllowedIncludes(mixed $include): static
    {
        $includes = $this->parsedIncludes($include);

        if ($includes !== []) {
            $this->load($includes);
        }

        return $this;
    }
}
