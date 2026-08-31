<?php

declare(strict_types=1);

namespace App\Services\Landlord\ApiKeys;

use App\Enums\Landlord\ApiKeyStatus;
use App\Models\Landlord\ApiKey;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Landlord API key ledger. Keys are hashed; plaintext is returned only on create.
 */
class ApiKeyService
{
    /**
     * Paginate API keys using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, ApiKey>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return ApiKey::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate API key select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return ApiKey::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (ApiKey $apiKey): array => [
                'label' => $apiKey->name,
                'value' => $apiKey->id,
            ]);
    }

    /**
     * Load an API key with optional allowed relationships.
     */
    public function show(ApiKey $apiKey, Request $request): ApiKey
    {
        return $apiKey->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Issue an active API key. The plaintext token is attached only on this instance.
     *
     * @param  array{user_id: int, name: string, expires_at?: string|null}  $data
     */
    public function store(array $data): ApiKey
    {
        [$token, $hash] = $this->nextToken();

        $apiKey = ApiKey::query()->create([
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'prefix' => substr($token, 0, 12),
            'key_hash' => $hash,
            'status' => ApiKeyStatus::Active,
            'expires_at' => isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            'last_used_at' => null,
            'revoked_at' => null,
        ])->load('user');

        $apiKey->plainTextToken = $token;

        return $apiKey;
    }

    /**
     * Update name or expiry on an active API key.
     *
     * @param  array{name?: string, expires_at?: string|null}  $data
     */
    public function update(ApiKey $apiKey, array $data): ApiKey
    {
        $this->ensureActive($apiKey);

        if (array_key_exists('expires_at', $data)) {
            $data['expires_at'] = $data['expires_at'] !== null
                ? Carbon::parse($data['expires_at'])
                : null;
        }

        $apiKey->update($data);

        return $apiKey->refresh();
    }

    /**
     * Revoke an active API key.
     */
    public function revoke(ApiKey $apiKey): ApiKey
    {
        $this->ensureActive($apiKey);

        $apiKey->update([
            'status' => ApiKeyStatus::Revoked,
            'revoked_at' => now(),
        ]);

        return $apiKey->refresh();
    }

    /**
     * Soft delete an API key.
     */
    public function destroy(ApiKey $apiKey): void
    {
        $apiKey->delete();
    }

    /**
     * Restore a soft-deleted API key.
     */
    public function restore(ApiKey $apiKey): ApiKey
    {
        abort_unless($apiKey->trashed(), 404);

        $apiKey->restore();

        return $apiKey->refresh();
    }

    /**
     * Soft delete many API keys.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        ApiKey::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted API keys.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        ApiKey::onlyTrashed()->whereKey($ids)->restore();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function nextToken(): array
    {
        do {
            $token = 'mrc_'.Str::random(40);
            $hash = hash('sha256', $token);
        } while (ApiKey::withTrashed()->where('key_hash', $hash)->exists());

        return [$token, $hash];
    }

    private function ensureActive(ApiKey $apiKey): void
    {
        if ($apiKey->status === ApiKeyStatus::Active) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'The API key is not active.',
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
