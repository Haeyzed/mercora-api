<?php

declare(strict_types=1);

use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use App\Notifications\Tenant\ResetPasswordNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
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

it('logs in a tenant user with a domain host and returns a token', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    $user = $context->createUser([
        'email' => 'admin@acme.test',
        'password' => 'password',
    ]);

    $this->postJson($context->url('/api/tenant/auth/login'), [
        'email' => 'admin@acme.test',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', 'admin@acme.test')
        ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);
});

it('logs in via X-Tenant-Domain on a central host', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    $user = $context->createUser([
        'email' => 'header@acme.test',
        'password' => 'password',
    ]);

    $this->postJson('https://mercora.test/api/tenant/auth/login', [
        'email' => 'header@acme.test',
        'password' => 'password',
    ], [
        InitializeTenancyByDomainOrHeader::HEADER => $context->domain,
    ])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id);
});

it('rejects inactive tenant users', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    $context->createUser([
        'email' => 'inactive@acme.test',
        'password' => 'password',
        'is_active' => false,
    ]);

    $this->postJson($context->url('/api/tenant/auth/login'), [
        'email' => 'inactive@acme.test',
        'password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects invalid credentials', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    $context->createUser([
        'email' => 'admin@acme.test',
        'password' => 'password',
    ]);

    $this->postJson($context->url('/api/tenant/auth/login'), [
        'email' => 'admin@acme.test',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns the authenticated user from me and logout revokes the token', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    $user = $context->createUser([
        'email' => 'me@acme.test',
        'password' => 'password',
    ]);

    $token = $this->postJson($context->url('/api/tenant/auth/login'), [
        'email' => 'me@acme.test',
        'password' => 'password',
    ])->json('data.token');

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'me@acme.test');

    $this->withToken($token)
        ->postJson($context->url('/api/tenant/auth/logout'))
        ->assertNoContent();

    $context->tenant->run(function (): void {
        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    auth()->forgetGuards();

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/auth/me'))
        ->assertUnauthorized();
});

it('updates the authenticated user profile', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    $context->createUser([
        'email' => 'profile@acme.test',
        'password' => 'password',
        'name' => 'Old Name',
    ]);

    $token = $this->postJson($context->url('/api/tenant/auth/login'), [
        'email' => 'profile@acme.test',
        'password' => 'password',
    ])->json('data.token');

    auth()->forgetGuards();

    $this->withToken($token)
        ->patchJson($context->url('/api/tenant/auth/profile'), [
            'name' => 'New Name',
            'phone' => '+15551234567',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.phone', '+15551234567');
});

it('changes password and revokes existing tokens', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    $context->createUser([
        'email' => 'pwd@acme.test',
        'password' => 'password',
    ]);

    $token = $this->postJson($context->url('/api/tenant/auth/login'), [
        'email' => 'pwd@acme.test',
        'password' => 'password',
    ])->json('data.token');

    auth()->forgetGuards();

    $this->withToken($token)
        ->postJson($context->url('/api/tenant/auth/change-password'), [
            'current_password' => 'password',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
        ->assertNoContent();

    $context->tenant->run(function (): void {
        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    auth()->forgetGuards();

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/auth/me'))
        ->assertUnauthorized();

    $this->postJson($context->url('/api/tenant/auth/login'), [
        'email' => 'pwd@acme.test',
        'password' => 'NewPassword1!',
    ])->assertOk();
});

it('sends a password reset notification and resets the password', function (): void {
    Notification::fake();

    $context = $this->tenantContext = provisionTenantContext();
    $user = $context->createUser([
        'email' => 'reset@acme.test',
        'password' => 'password',
    ]);

    $this->postJson($context->url('/api/tenant/auth/forgot-password'), [
        'email' => 'reset@acme.test',
    ])->assertNoContent();

    Notification::assertSentTo($user, ResetPasswordNotification::class);

    $token = $context->tenant->run(function () use ($user): string {
        return Password::broker('tenant_users')->createToken($user->fresh());
    });

    $this->postJson($context->url('/api/tenant/auth/reset-password'), [
        'email' => 'reset@acme.test',
        'token' => $token,
        'password' => 'ResetPassword1!',
        'password_confirmation' => 'ResetPassword1!',
    ])->assertNoContent();

    $this->postJson($context->url('/api/tenant/auth/login'), [
        'email' => 'reset@acme.test',
        'password' => 'ResetPassword1!',
    ])->assertOk();
});

it('blocks central host without X-Tenant-Domain for tenant auth', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    $context->createUser([
        'email' => 'admin@acme.test',
        'password' => 'password',
    ]);

    $this->postJson('https://mercora.test/api/tenant/auth/login', [
        'email' => 'admin@acme.test',
        'password' => 'password',
    ])->assertNotFound();
});
