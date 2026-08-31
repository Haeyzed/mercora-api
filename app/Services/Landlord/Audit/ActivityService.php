<?php

declare(strict_types=1);

namespace App\Services\Landlord\Audit;

use App\Models\Landlord\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Landlord activity log. Clients can list, inspect, and purge rows; they cannot invent them.
 */
class ActivityService
{
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
     * Paginate activity select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return Activity::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Activity $activity): array => [
                'label' => $activity->description,
                'value' => $activity->id,
            ]);
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

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
