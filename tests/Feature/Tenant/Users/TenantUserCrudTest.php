<?php

declare(strict_types=1);

use App\Enums\Landlord\FeatureType;
use App\Enums\Landlord\SubscriptionStatus;
use App\Enums\Tenant\RoleName;
use App\Models\Landlord\Feature;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Tenant\User;
use App\Services\Landlord\Plans\UsageLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\TenantTestContext;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config([
        'tenancy.central_domains' => ['mercora.test', 'localhost', '127.0.0.1'],
    ]);

    RateLimiter::for('tenant-auth', fn (): Limit => Limit::none());
    RateLimiter::for('tenant-api', fn (): Limit => Limit::none());
});

afterEach(function (): void {
    if (isset($this->tenantContext) && $this->tenantContext instanceof TenantTestContext) {
        $this->tenantContext->tearDown();
    }
});

/**
 * @return array{0: TenantTestContext, 1: string}
 */
function provisionAdminWithSeats(int $seatLimit, SubscriptionStatus $status = SubscriptionStatus::Active): array
{
    $context = TenantTestContext::provisionWithAdmin([
        'name' => 'Admin User',
        'email' => 'admin@acme.test',
        'password' => 'password',
    ]);

    $plan = Plan::factory()->active()->create();
    $feature = Feature::factory()->create([
        'key' => UsageLimiter::FEATURE_USERS_MAX,
        'type' => FeatureType::Integer,
        'is_active' => true,
    ]);
    $plan->features()->attach($feature->id, ['value' => (string) $seatLimit]);

    $factory = Subscription::factory()->for($context->tenant)->for($plan);

    if ($status === SubscriptionStatus::PastDue) {
        $factory->pastDue()->create();
    } else {
        $factory->create([
            'status' => $status,
            'is_current' => 1,
        ]);
    }

    $token = test()->postJson($context->url('/api/tenant/auth/login'), [
        'email' => 'admin@acme.test',
        'password' => 'password',
    ])->json('data.token');

    auth()->forgetGuards();

    return [$context, $token];
}

it('creates a staff user when seats remain', function (): void {
    [$context, $token] = provisionAdminWithSeats(2);
    $this->tenantContext = $context;

    $this->withToken($token)
        ->postJson($context->url('/api/tenant/users'), [
            'name' => 'Staff One',
            'email' => 'staff1@acme.test',
            'password' => 'password',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'staff1@acme.test')
        ->assertJsonPath('data.roles.0', RoleName::Staff->value);
});

it('returns 422 when the seat cap is reached', function (): void {
    [$context, $token] = provisionAdminWithSeats(2);
    $this->tenantContext = $context;

    $this->withToken($token)
        ->postJson($context->url('/api/tenant/users'), [
            'name' => 'Staff One',
            'email' => 'staff1@acme.test',
            'password' => 'password',
        ])
        ->assertCreated();

    auth()->forgetGuards();

    $this->withToken($token)
        ->postJson($context->url('/api/tenant/users'), [
            'name' => 'Staff Two',
            'email' => 'staff2@acme.test',
            'password' => 'password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['feature']);
});

it('returns 402 before seat checks when the subscription is past due', function (): void {
    [$context, $token] = provisionAdminWithSeats(5, SubscriptionStatus::PastDue);
    $this->tenantContext = $context;

    $this->withToken($token)
        ->postJson($context->url('/api/tenant/users'), [
            'name' => 'Staff One',
            'email' => 'staff1@acme.test',
            'password' => 'password',
        ])
        ->assertPaymentRequired()
        ->assertJsonPath('message', 'An active subscription is required.');
});

it('forbids store for staff without users.manage', function (): void {
    [$context, $token] = provisionAdminWithSeats(5);
    $this->tenantContext = $context;

    $this->withToken($token)
        ->postJson($context->url('/api/tenant/users'), [
            'name' => 'Staff One',
            'email' => 'staff1@acme.test',
            'password' => 'password',
        ])
        ->assertCreated();

    auth()->forgetGuards();

    $staffToken = $this->postJson($context->url('/api/tenant/auth/login'), [
        'email' => 'staff1@acme.test',
        'password' => 'password',
    ])->json('data.token');

    auth()->forgetGuards();

    $this->withToken($staffToken)
        ->postJson($context->url('/api/tenant/users'), [
            'name' => 'Staff Two',
            'email' => 'staff2@acme.test',
            'password' => 'password',
        ])
        ->assertForbidden();
});

it('lists users for admins with users.view', function (): void {
    [$context, $token] = provisionAdminWithSeats(5);
    $this->tenantContext = $context;

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/users'))
        ->assertOk()
        ->assertJsonPath('data.0.email', 'admin@acme.test');
});

it('prevents deleting the last admin', function (): void {
    [$context, $token] = provisionAdminWithSeats(5);
    $this->tenantContext = $context;

    $adminId = $context->tenant->run(
        fn (): int => User::query()->where('email', 'admin@acme.test')->value('id'),
    );

    $this->withToken($token)
        ->deleteJson($context->url("/api/tenant/users/{$adminId}"))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});
