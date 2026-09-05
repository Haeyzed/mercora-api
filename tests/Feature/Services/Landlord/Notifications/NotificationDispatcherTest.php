<?php

use App\Enums\Landlord\NoticeChannel;
use App\Models\Landlord\Notice;
use App\Models\Landlord\NotificationPreference;
use App\Models\Landlord\NotificationTemplate;
use App\Models\Landlord\User;
use App\Services\Landlord\Notifications\NotificationDispatcher;
use App\Services\Landlord\SettingService;
use Database\Seeders\Landlord\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    actingAsLandlord();
    $this->seed(NotificationTemplateSeeder::class);
});

it('fans out templated notices to active users', function () {
    $other = landlordUser(['is_active' => true]);

    $count = app(NotificationDispatcher::class)->notifyActiveUsers('payment.successful', [
        'reference' => 'pay_1',
        'amount' => '10.00',
        'currency' => 'USD',
    ]);

    expect($count)->toBeGreaterThan(0)
        ->and(Notice::query()->where('title', 'Payment successful')->where('channel', NoticeChannel::InApp)->count())
        ->toBeGreaterThanOrEqual(2)
        ->and(Notice::query()->where('user_id', $other->id)->where('title', 'Payment successful')->exists())->toBeTrue();
});

it('skips disabled preferences for optional templates', function () {
    $user = auth()->user();

    NotificationPreference::query()->create([
        'user_id' => $user->id,
        'notification_key' => 'payment.successful',
        'channel' => NoticeChannel::InApp->value,
        'enabled' => false,
    ]);

    NotificationPreference::query()->create([
        'user_id' => $user->id,
        'notification_key' => 'payment.successful',
        'channel' => NoticeChannel::Mail->value,
        'enabled' => false,
    ]);

    User::query()->whereKeyNot($user->id)->update(['is_active' => false]);

    $count = app(NotificationDispatcher::class)->send($user, 'payment.successful', [
        'reference' => 'pay_1',
        'amount' => '10.00',
        'currency' => 'USD',
    ]);

    expect($count)->toBe(0)
        ->and(Notice::query()->where('user_id', $user->id)->where('title', 'Payment successful')->count())->toBe(0);
});

it('respects billing alert kill switch', function () {
    app(SettingService::class)->updateDomain('notifications', [
        'notifications.billing_alerts' => false,
    ]);

    $count = app(NotificationDispatcher::class)->notifyActiveUsers('payment.successful', [
        'reference' => 'pay_1',
        'amount' => '10.00',
        'currency' => 'USD',
    ]);

    expect($count)->toBe(0)
        ->and(Notice::query()->where('title', 'Payment successful')->count())->toBe(0);
});

it('is a no-op for missing templates', function () {
    NotificationTemplate::query()->where('key', 'payment.successful')->delete();

    $count = app(NotificationDispatcher::class)->notifyActiveUsers('payment.successful', [
        'reference' => 'pay_1',
        'amount' => '10.00',
        'currency' => 'USD',
    ]);

    expect($count)->toBe(0);
});
