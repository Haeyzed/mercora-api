<?php

use App\Enums\Landlord\InvoiceStatus;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Notice;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\BillingOpsService;
use App\Services\Landlord\SettingService;
use App\Services\Landlord\Tenants\TenantService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    actingAsLandlord();
    Cache::flush();
});

describe('billing reminders', function () {
    it('sends a due-soon reminder for open invoices', function () {
        $this->travelTo('2026-09-10 12:00:00');

        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Open,
            'number' => 'INV-DUE-1',
            'due_at' => now()->addDays(2),
        ]);

        Artisan::call('landlord:send-billing-reminders');

        expect(Notice::query()->where('title', 'Invoice due soon')->count())->toBeGreaterThan(0);

        $count = Notice::query()->where('title', 'Invoice due soon')->count();
        Artisan::call('landlord:send-billing-reminders');
        expect(Notice::query()->where('title', 'Invoice due soon')->count())->toBe($count);
        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
    });

    it('sends an overdue reminder after the overdue interval', function () {
        $this->travelTo('2026-09-20 12:00:00');

        app(SettingService::class)->updateDomain('billing', [
            'billing.overdue_reminder_days' => 7,
        ]);

        Invoice::factory()->create([
            'status' => InvoiceStatus::Open,
            'number' => 'INV-OVER-1',
            'due_at' => now()->subDays(8),
        ]);

        Artisan::call('landlord:send-billing-reminders');

        expect(Notice::query()->where('title', 'Invoice overdue')->count())->toBeGreaterThan(0);
    });

    it('sends a renewal reminder for active subscriptions nearing period end', function () {
        $this->travelTo('2026-09-10 12:00:00');

        Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'ends_at' => now()->addDays(5),
            'canceled_at' => null,
        ]);

        Artisan::call('landlord:send-billing-reminders');

        expect(Notice::query()->where('title', 'Subscription renewing soon')->count())->toBeGreaterThan(0);
    });
});

describe('dunning', function () {
    it('sends a dunning notice for past-due subscriptions after the interval', function () {
        $this->travelTo('2026-09-20 12:00:00');

        app(SettingService::class)->updateDomain('subscriptions', [
            'subscriptions.dunning_enabled' => true,
            'subscriptions.dunning_attempts' => 3,
            'subscriptions.dunning_interval_days' => 3,
        ]);

        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::PastDue,
            'ends_at' => now()->subDays(4),
            'dunning_attempts' => 0,
            'last_dunned_at' => null,
        ]);

        Artisan::call('landlord:process-dunning');

        expect($subscription->fresh()->dunning_attempts)->toBe(1)
            ->and($subscription->fresh()->last_dunned_at)->not->toBeNull()
            ->and(Notice::query()->where('title', 'Payment dunning reminder')->count())->toBeGreaterThan(0);
    });

    it('skips dunning when disabled', function () {
        app(SettingService::class)->updateDomain('subscriptions', [
            'subscriptions.dunning_enabled' => false,
        ]);

        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::PastDue,
            'ends_at' => now()->subDays(10),
            'dunning_attempts' => 0,
        ]);

        expect(app(BillingOpsService::class)->processDunning())->toBe(0)
            ->and($subscription->fresh()->dunning_attempts)->toBe(0);
    });
});

describe('tenant purge', function () {
    it('force-deletes soft-deleted tenants past retention', function () {
        $this->travelTo('2026-09-20 12:00:00');

        app(SettingService::class)->updateDomain('tenancy', [
            'tenancy.soft_delete_retention_days' => 30,
        ]);

        $expired = Tenant::factory()->create(['name' => 'Expired Tenant']);
        $expired->delete();
        Tenant::withTrashed()->whereKey($expired->id)->update([
            'deleted_at' => now()->subDays(31),
        ]);

        $recent = Tenant::factory()->create(['name' => 'Recent Tenant']);
        $recent->delete();
        Tenant::withTrashed()->whereKey($recent->id)->update([
            'deleted_at' => now()->subDays(5),
        ]);

        $purged = app(TenantService::class)->purgeExpiredSoftDeletes();

        expect($purged)->toBe(1)
            ->and(Tenant::withTrashed()->whereKey($expired->id)->exists())->toBeFalse()
            ->and(Tenant::onlyTrashed()->whereKey($recent->id)->exists())->toBeTrue();
    });
});
