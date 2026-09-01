<?php

declare(strict_types=1);

namespace App\Services\Landlord\Settings;

use App\Enums\Landlord\SettingType;
use App\Models\Landlord\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Manages landlord platform settings with typed values and cache-backed reads.
 *
 * Domain: key/value configuration for the landlord application.
 *
 * Invariants:
 * - Setting keys are immutable after creation.
 * - Values are encoded/decoded according to {@see SettingType}.
 * - Cached reads use the key `landlord.setting.{key}` with a one-hour TTL.
 *
 * Side effects: creates, updates, soft-deletes, and restores {@see Setting} records; invalidates cache on writes.
 */
class SettingService
{
    /**
     * Paginate settings using model filter, search, and ordered scopes.
     *
     * @return LengthAwarePaginator<int, Setting>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Setting::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Create a platform setting.
     *
     * @param  array{group: string, key: string, type: SettingType|string, value: mixed, description?: string|null}  $data
     */
    public function store(array $data): Setting
    {
        $type = $this->typeFrom($data['type']);

        $setting = Setting::query()->create([
            'group' => $data['group'],
            'key' => $data['key'],
            'type' => $type,
            'value' => Setting::encode($type, $data['value']),
            'description' => $data['description'] ?? null,
        ]);

        $this->forgetCache($setting);

        return $setting;
    }

    /**
     * Update a platform setting. The key is immutable.
     *
     * @param  array{group?: string, type?: SettingType|string, value?: mixed, description?: string|null}  $data
     */
    public function update(Setting $setting, array $data): Setting
    {
        $type = isset($data['type']) ? $this->typeFrom($data['type']) : $setting->type;

        if (array_key_exists('value', $data) || isset($data['type'])) {
            $data['value'] = Setting::encode(
                $type,
                array_key_exists('value', $data) ? $data['value'] : $setting->decoded(),
            );
        }

        $data['type'] = $type;
        unset($data['key']);

        $setting->update($data);

        $this->forgetCache($setting);

        return $setting->refresh();
    }

    /**
     * Soft delete a setting.
     */
    public function destroy(Setting $setting): void
    {
        $setting->delete();
        $this->forgetCache($setting);
    }

    /**
     * Restore a soft-deleted setting.
     *
     * @throws HttpException When the setting is not trashed (404).
     */
    public function restore(Setting $setting): Setting
    {
        abort_unless($setting->trashed(), 404);

        $setting->restore();
        $this->forgetCache($setting);

        return $setting->refresh();
    }

    /**
     * Soft delete many settings.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Setting::query()->whereKey($ids)->get()->each(fn (Setting $setting) => $this->forgetCache($setting));
        Setting::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted settings.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        Setting::onlyTrashed()->whereKey($ids)->restore();
        Setting::query()->whereKey($ids)->get()->each(fn (Setting $setting) => $this->forgetCache($setting));
    }

    /**
     * Read a decoded setting value by key, with optional default when missing.
     *
     * Results are cached for one hour.
     */
    public function value(string $key, mixed $default = null): mixed
    {
        return Cache::remember('landlord.setting.'.$key, now()->addHour(), function () use ($key, $default): mixed {
            $setting = Setting::query()->where('key', $key)->first();

            return $setting instanceof Setting ? $setting->decoded() : $default;
        });
    }

    /**
     * Invalidate the cache entry for a setting key.
     */
    private function forgetCache(Setting $setting): void
    {
        Cache::forget('landlord.setting.'.$setting->key);
    }

    /**
     * Normalize a setting type to the {@see SettingType} enum.
     */
    private function typeFrom(SettingType|string $type): SettingType
    {
        return $type instanceof SettingType ? $type : SettingType::from($type);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
