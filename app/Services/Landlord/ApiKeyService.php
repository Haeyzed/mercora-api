<?php

declare(strict_types=1);

namespace App\Services\Landlord;

use App\Enums\Landlord\ApiKeyStatus;
use App\Models\Landlord\ApiKey;
use App\Services\Concerns\PaginatesRequests;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Manages landlord API keys for programmatic access.
 *
 * Domain: landlord authentication credentials scoped to a user.
 *
 * Invariants:
 * - Only the SHA-256 hash of a key is persisted; plaintext is exposed once on creation via {@see ApiKey::$plainTextToken}.
 * - Revoked or inactive keys cannot be updated.
 * - Token prefixes are unique across soft-deleted rows.
 * - Creation respects {@see api.keys_enabled}, {@see api.max_keys_per_user}, and default TTL settings.
 *
 * Side effects: creates, updates, revokes, soft-deletes, and restores {@see ApiKey} records;
 * reads {@see SettingService} for API policy.
 */
class ApiKeyService
{
    use PaginatesRequests;

    public function __construct(private SettingService $settings) {}

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
     *
     * @throws ValidationException When API keys are disabled or the user is at their key limit.
     */
    public function store(array $data): ApiKey
    {
        $this->ensureApiKeysEnabled();
        $this->ensureWithinKeyLimit((int) $data['user_id']);

        [$token, $hash] = $this->nextToken();

        $apiKey = ApiKey::query()->create([
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'prefix' => substr($token, 0, 12),
            'key_hash' => $hash,
            'status' => ApiKeyStatus::Active,
            'expires_at' => $this->resolveExpiresAt($data['expires_at'] ?? null),
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
     *
     * @throws ValidationException When the key is not active.
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
     *
     * @throws ValidationException When the key is not active.
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
     *
     * @throws HttpException When the key is not trashed (404).
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
     * Generate a unique plaintext token and its SHA-256 hash.
     *
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

    /**
     * Ensure the API key is in active status before mutating.
     *
     * @throws ValidationException When the key is not active.
     */
    private function ensureActive(ApiKey $apiKey): void
    {
        if ($apiKey->status === ApiKeyStatus::Active) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'The API key is not active.',
        ]);
    }

    /**
     * @throws ValidationException When API key creation is disabled.
     */
    private function ensureApiKeysEnabled(): void
    {
        if ($this->settings->value('api.keys_enabled', true)) {
            return;
        }

        throw ValidationException::withMessages([
            'name' => ['API key creation is disabled.'],
        ]);
    }

    /**
     * @throws ValidationException When the user already has the maximum number of active keys.
     */
    private function ensureWithinKeyLimit(int $userId): void
    {
        $max = max(1, (int) $this->settings->value('api.max_keys_per_user', 10));
        $count = ApiKey::query()
            ->where('user_id', $userId)
            ->where('status', ApiKeyStatus::Active)
            ->count();

        if ($count < $max) {
            return;
        }

        throw ValidationException::withMessages([
            'user_id' => ["This user may have at most {$max} active API keys."],
        ]);
    }

    /**
     * Resolve expiry from the request or default TTL settings.
     *
     * A TTL of 0 means keys do not expire by default unless require_key_expiry is on.
     */
    private function resolveExpiresAt(mixed $expiresAt): ?CarbonInterface
    {
        if ($expiresAt !== null && $expiresAt !== '') {
            return Carbon::parse((string) $expiresAt);
        }

        $ttlDays = max(0, (int) $this->settings->value('api.default_key_ttl_days', 365));
        $requireExpiry = (bool) $this->settings->value('api.require_key_expiry', false);

        if ($ttlDays === 0 && ! $requireExpiry) {
            return null;
        }

        $days = $ttlDays > 0 ? $ttlDays : 365;

        return now()->addDays($days);
    }
}
