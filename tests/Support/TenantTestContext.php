<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Landlord\TenantStatus;
use App\Jobs\Landlord\FinalizeTenantProvision;
use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Stancl\Tenancy\Jobs\SeedDatabase;
use Stancl\Tenancy\Tenancy;

/**
 * Disposable tenant database for feature tests that need real tenancy bootstrappers.
 */
final class TenantTestContext
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $domain,
    ) {}

    /**
     * Create a landlord tenant + domain, provision a SQLite file, and migrate.
     *
     * @param  array<string, mixed>  $tenantAttributes
     */
    public static function provision(TenantStatus $status = TenantStatus::Active, array $tenantAttributes = []): self
    {
        $tenant = Tenant::factory()->create([
            'status' => $status,
            'provisioned_at' => in_array($status, [TenantStatus::Active, TenantStatus::Suspended], true)
                ? now()
                : null,
            ...$tenantAttributes,
        ]);

        $domain = 'auth-'.uniqid('', true).'.example.test';
        Domain::factory()->for($tenant)->create(['domain' => $domain]);

        $tenant = $tenant->fresh();

        try {
            (new CreateDatabase($tenant))->handle(app(DatabaseManager::class));
        } catch (\Throwable $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'already')) {
                throw $exception;
            }
        }

        (new MigrateDatabase($tenant))->handle();

        return new self($tenant->fresh(), $domain);
    }

    /**
     * Migrate, seed RBAC, and finalize admin from pending_provision (or the given admin).
     *
     * @param  array{name: string, email: string, password: string, phone?: string|null}  $admin
     */
    public static function provisionWithAdmin(array $admin, TenantStatus $status = TenantStatus::Pending): self
    {
        $context = self::provision($status, [
            'pending_provision' => [
                'admin' => $admin,
                'intended_status' => TenantStatus::Active->value,
            ],
            'provisioned_at' => null,
        ]);

        (new SeedDatabase($context->tenant))->handle();
        (new FinalizeTenantProvision($context->tenant))->handle();

        $tenant = $context->tenant->fresh();
        $tenant?->forceFill([
            'status' => TenantStatus::Active,
            'provisioned_at' => now(),
            'provision_error' => null,
        ])->save();

        return new self($tenant->fresh(), $context->domain);
    }

    /**
     * Seed RBAC only (no users).
     */
    public function seedRbac(): void
    {
        (new SeedDatabase($this->tenant))->handle();
    }

    /**
     * Create a tenant staff user inside the tenant database.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = $this->tenant->run(fn (): User => User::factory()->create($attributes));

        return $user;
    }

    /**
     * Absolute HTTPS URL for a tenant API path on this tenant's domain.
     */
    public function url(string $path): string
    {
        return 'https://'.$this->domain.'/'.ltrim($path, '/');
    }

    /**
     * End tenancy and delete the disposable tenant database file.
     */
    public function tearDown(): void
    {
        $tenancy = app(Tenancy::class);

        if ($tenancy->initialized) {
            $tenancy->end();
        }

        $tenant = $this->tenant->fresh();

        if ($tenant === null) {
            return;
        }

        try {
            $database = $tenant->database()->getName();
            $path = database_path($database);

            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        } catch (\Throwable) {
            // Best-effort cleanup for disposable SQLite files.
        }

        $tenant->forceDelete();
    }
}
