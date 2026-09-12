<?php

declare(strict_types=1);

use App\Enums\Landlord\FeatureType;
use App\Models\Landlord\Feature;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Tenant\User;
use App\Services\Landlord\Plans\UsageLimiter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantTestContext;

uses(LazilyRefreshDatabase::class);

afterEach(function (): void {
    if (isset($this->tenantContext) && $this->tenantContext instanceof TenantTestContext) {
        $this->tenantContext->tearDown();
    }
});

it('reports remaining seats against live user counts', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    $plan = Plan::factory()->active()->create();
    $feature = Feature::factory()->create([
        'key' => UsageLimiter::FEATURE_USERS_MAX,
        'type' => FeatureType::Integer,
        'is_active' => true,
    ]);
    $plan->features()->attach($feature->id, ['value' => '3']);
    Subscription::factory()->for($context->tenant)->for($plan)->create();

    $context->tenant->run(function (): void {
        User::factory()->count(2)->create();
    });

    $limiter = app(UsageLimiter::class);

    $context->tenant->run(function () use ($limiter, $context): void {
        expect($limiter->currentUsage(UsageLimiter::FEATURE_USERS_MAX))->toBe(2)
            ->and($limiter->remaining($context->tenant, UsageLimiter::FEATURE_USERS_MAX))->toBe(1)
            ->and($limiter->canCreate($context->tenant, UsageLimiter::FEATURE_USERS_MAX))->toBeTrue();
    });
});

it('treats unlimited seats as creatable without a numeric remaining', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    $plan = Plan::factory()->active()->create();
    $feature = Feature::factory()->create([
        'key' => UsageLimiter::FEATURE_USERS_MAX,
        'type' => FeatureType::Unlimited,
        'is_active' => true,
    ]);
    $plan->features()->attach($feature->id, ['value' => 'unlimited']);
    Subscription::factory()->for($context->tenant)->for($plan)->create();

    $limiter = app(UsageLimiter::class);

    $context->tenant->run(function () use ($limiter, $context): void {
        expect($limiter->remaining($context->tenant, UsageLimiter::FEATURE_USERS_MAX))->toBeNull()
            ->and($limiter->canCreate($context->tenant, UsageLimiter::FEATURE_USERS_MAX))->toBeTrue();
    });
});

it('throws when asserting create beyond the seat cap', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    $plan = Plan::factory()->active()->create();
    $feature = Feature::factory()->create([
        'key' => UsageLimiter::FEATURE_USERS_MAX,
        'type' => FeatureType::Integer,
        'is_active' => true,
    ]);
    $plan->features()->attach($feature->id, ['value' => '1']);
    Subscription::factory()->for($context->tenant)->for($plan)->create();

    $context->tenant->run(function (): void {
        User::factory()->create();
    });

    $limiter = app(UsageLimiter::class);

    $context->tenant->run(function () use ($limiter, $context): void {
        $limiter->assertCanCreate($context->tenant, UsageLimiter::FEATURE_USERS_MAX);
    });
})->throws(ValidationException::class);
