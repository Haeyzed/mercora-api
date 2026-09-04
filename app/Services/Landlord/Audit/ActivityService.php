<?php

declare(strict_types=1);

namespace App\Services\Landlord\Audit;

use App\Models\Landlord\Activity;
use App\Services\Concerns\PaginatesRequests;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Read and purge landlord activity log entries.
 *
 * Domain: audit trail produced by Spatie Activity Log and manual logging.
 *
 * Invariants:
 * - Activities are append-only from the API perspective; clients cannot create or edit rows.
 * - Deletion is permanent (no soft deletes).
 *
 * Side effects: permanently deletes {@see Activity} records when requested.
 */
class ActivityService
{
    use PaginatesRequests;

    /**
     * Paginate activities using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, Activity>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Activity::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Load an activity with optional allowed relationships.
     */
    public function show(Activity $activity, Request $request): Activity
    {
        return $activity->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Permanently delete an activity.
     */
    public function destroy(Activity $activity): void
    {
        $activity->delete();
    }

    /**
     * Permanently delete many activities.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Activity::query()->whereKey($ids)->delete();
    }
}
