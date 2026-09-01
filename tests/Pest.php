<?php

use App\Enums\Landlord\RoleName;
use App\Models\Landlord\User;
use App\Support\Landlord\Authorization;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature');

uses()->beforeEach(function (): void {
    actingAsLandlord();
})->in(
    'Feature/Http/Controllers/Shared/World',
    'Feature/Http/Controllers/Landlord/Tenants',
    'Feature/Http/Controllers/Landlord/Plans',
    'Feature/Http/Controllers/Landlord/Payments',
    'Feature/Http/Controllers/Landlord/Subscriptions',
    'Feature/Http/Controllers/Landlord/Billing',
    'Feature/Http/Controllers/Landlord/Settings',
    'Feature/Http/Controllers/Landlord/Notifications',
    'Feature/Http/Controllers/Landlord/ApiKeys',
    'Feature/Http/Controllers/Landlord/Audit',
    'Feature/Http/Controllers/Landlord/Users',
    'Feature/Http/Controllers/Landlord/Roles',
);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function landlordUser(array $overrides = []): User
{
    return activity()->withoutLogging(fn (): User => User::factory()->create($overrides));
}

function actingAsLandlord(?User $user = null, bool $superAdmin = true): User
{
    Authorization::seed();

    $user ??= landlordUser();

    if ($superAdmin && ! $user->hasRole(RoleName::SuperAdmin->value)) {
        $user->assignRole(RoleName::SuperAdmin->value);
    }

    Sanctum::actingAs($user);

    return $user;
}

function configureFlutterwaveForTests(): void
{
    config([
        'payments.drivers.flutterwave.secret_key' => 'test-secret',
        'payments.drivers.flutterwave.public_key' => 'test-public',
        'payments.drivers.flutterwave.secret_hash' => 'test-hash',
        'payments.drivers.flutterwave.base_url' => 'https://api.flutterwave.com/v3',
    ]);
}

function fakeFlutterwaveInitialize(string $checkoutUrl = 'https://checkout.test/pay'): void
{
    Http::fake([
        'https://api.flutterwave.com/v3/payments' => Http::response([
            'status' => 'success',
            'data' => [
                'id' => '999',
                'link' => $checkoutUrl,
            ],
        ]),
    ]);
}

function fakeFlutterwaveVerify(string $reference, int $amountMinor, string $currency = 'USD', string $status = 'successful'): void
{
    Http::fake([
        'https://api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
            'status' => 'success',
            'data' => [
                'tx_ref' => $reference,
                'status' => $status,
                'amount' => $amountMinor / 100,
                'currency' => $currency,
                'id' => '888',
            ],
        ]),
    ]);
}
