<?php

use App\Models\Landlord\User;
use App\Services\Landlord\SettingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('EnsurePlatformNotInMaintenance', function () {
    it('returns 503 for guest landlord routes when maintenance mode is on', function () {
        app(SettingService::class)->updateDomain('platform', [
            'platform.maintenance_mode' => true,
            'platform.maintenance_message' => 'Back soon.',
        ]);

        $this->getJson('/api/landlord/users')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Back soon.');
    });

    it('still allows login during maintenance', function () {
        app(SettingService::class)->updateDomain('platform', [
            'platform.maintenance_mode' => true,
        ]);

        $user = User::factory()->create([
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->postJson('/api/landlord/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test',
        ])->assertOk();
    });

    it('allows authenticated requests during maintenance', function () {
        app(SettingService::class)->updateDomain('platform', [
            'platform.maintenance_mode' => true,
        ]);

        actingAsLandlord();

        $this->getJson('/api/landlord/settings')->assertOk();
    });
});
