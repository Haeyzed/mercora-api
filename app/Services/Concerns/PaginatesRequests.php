<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Http\Request;

/**
 * Shared pagination sizing for list endpoints.
 *
 * Default 15, minimum 1, maximum 100 from the request `per_page` query parameter.
 */
trait PaginatesRequests
{
    /**
     * Resolve a clamped page size from the request.
     */
    protected function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
