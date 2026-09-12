<?php

use App\Enums\Landlord\NotificationChannel;
use App\Models\Landlord\NotificationPreference;
use Database\Seeders\Landlord\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(NotificationTemplateSeeder::class);
});

describe('index', function () {
    it('lists effective preferences for active templates', function () {
        $this->getJson('/api/landlord/notification-preferences')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'notification_key',
                        'name',
                        'is_mandatory',
                        'channels' => [
                            ['channel', 'enabled', 'locked'],
                        ],
                    ],
                ],
            ]);
    });
});

describe('update', function () {
    it('syncs preferences and locks mandatory channels', function () {
        $user = auth()->user();

        $this->putJson('/api/landlord/notification-preferences', [
            'preferences' => [
                [
                    'notification_key' => 'payment.successful',
                    'channel' => NotificationChannel::InApp->value,
                    'enabled' => false,
                ],
                [
                    'notification_key' => 'auth.welcome',
                    'channel' => NotificationChannel::InApp->value,
                    'enabled' => false,
                ],
            ],
        ])
            ->assertOk();

        expect(
            NotificationPreference::query()
                ->where('user_id', $user->id)
                ->where('notification_key', 'payment.successful')
                ->where('channel', NotificationChannel::InApp->value)
                ->value('enabled')
        )->toBeFalse()
            ->and(
                NotificationPreference::query()
                    ->where('user_id', $user->id)
                    ->where('notification_key', 'auth.welcome')
                    ->where('channel', NotificationChannel::InApp->value)
                    ->value('enabled')
            )->toBeTrue();

        $welcome = collect($this->getJson('/api/landlord/notification-preferences')->json('data'))
            ->firstWhere('notification_key', 'auth.welcome');

        $inApp = collect($welcome['channels'])->firstWhere('channel', NotificationChannel::InApp->value);
        $push = collect($welcome['channels'])->firstWhere('channel', NotificationChannel::Push->value);

        expect($inApp['locked'])->toBeTrue()
            ->and($inApp['enabled'])->toBeTrue()
            ->and($push['locked'])->toBeFalse();
    });
});
