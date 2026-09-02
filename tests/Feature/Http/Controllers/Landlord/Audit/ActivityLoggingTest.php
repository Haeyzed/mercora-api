<?php

use App\Enums\Landlord\RoleName;
use App\Enums\Landlord\SettingType;
use App\Jobs\Landlord\ProvisionTenantJob;
use App\Models\Landlord\Activity;
use App\Models\Landlord\ApiKey;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Payment;
use App\Models\Landlord\Setting;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(LazilyRefreshDatabase::class);

it('logs tenant creation without exposing provision errors', function () {
    Bus::fake([ProvisionTenantJob::class]);

    $this->postJson('/api/landlord/tenants', [
        'name' => 'Acme Stores',
        'domain' => 'acme.example.com',
    ])->assertCreated();

    $tenant = Tenant::query()->where('name', 'Acme Stores')->first();

    expect(Activity::query()->forSubject($tenant)->where('event', 'created')->exists())->toBeTrue();
});

it('logs subscription cancellation', function () {
    $subscription = Subscription::factory()->create();

    $this->postJson("/api/landlord/subscriptions/{$subscription->id}/cancel")
        ->assertOk();

    expect(Activity::query()->forSubject($subscription)->where('event', 'updated')->exists())->toBeTrue();
});

it('logs payment initiation for invoices', function () {
    configureFlutterwaveForTests();
    fakeFlutterwaveInitialize();

    $invoice = Invoice::factory()->create();

    $this->postJson("/api/landlord/invoices/{$invoice->id}/pay")
        ->assertCreated();

    $payment = Payment::query()->where('invoice_id', $invoice->id)->first();

    expect(Activity::query()->forSubject($payment)->where('event', 'created')->exists())->toBeTrue();
});

it('does not store API key hashes in activity attributes', function () {
    $user = User::factory()->create();

    $this->postJson('/api/landlord/api-keys', [
        'user_id' => $user->id,
        'name' => 'CI key',
    ])->assertCreated();

    $apiKey = ApiKey::query()->first();
    $activity = Activity::query()->forSubject($apiKey)->where('event', 'created')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->toArray())->not->toHaveKey('attributes.key_hash')
        ->and(json_encode($activity->properties))->not->toContain($apiKey->key_hash);
});

it('does not store setting values in activity attributes', function () {
    Setting::query()->create([
        'group' => 'platform',
        'key' => 'platform.name',
        'type' => SettingType::String,
        'value' => 'secret-setting-value',
    ]);

    $this->putJson('/api/landlord/settings/platform', [
        'platform.name' => 'secret-setting-value',
        'platform.support_phone' => 'Updated',
    ])->assertOk();

    $setting = Setting::query()->where('key', 'platform.support_phone')->first();
    $activity = Activity::query()->forSubject($setting)->where('event', 'created')->first()
        ?? Activity::query()->forSubject($setting)->where('event', 'updated')->first();

    expect(json_encode($activity?->properties))->not->toContain('secret-setting-value');
});

it('returns 403 when a user without purge permission deletes an activity', function () {
    $actor = User::factory()->create();
    actingAsLandlord($actor, superAdmin: false);
    $actor->assignRole(RoleName::Operator->value);

    $activity = Activity::factory()->create();

    $this->deleteJson("/api/landlord/activities/{$activity->id}")
        ->assertForbidden();
});
