<?php

use App\Enums\Landlord\InvoiceStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\SettingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('returns a paginated list of invoices', function () {
        Invoice::factory()->count(2)->create();

        $this->getJson('/api/landlord/invoices')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'tenant_id', 'subscription_id', 'number', 'status', 'amount', 'currency'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    });

    it('paginates invoices using the per_page query parameter', function () {
        Invoice::factory()->count(3)->create();

        $this->getJson('/api/landlord/invoices?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    });

    it('filters invoices by tenant id', function () {
        $subscription = Subscription::factory()->create();
        Invoice::factory()->for($subscription)->create();
        Invoice::factory()->create();

        $this->getJson('/api/landlord/invoices?filter[tenant_id]='.$subscription->tenant_id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.tenant_id', $subscription->tenant_id);
    });

    it('filters invoices by status', function () {
        Invoice::factory()->paid()->create();
        Invoice::factory()->create();

        $this->getJson('/api/landlord/invoices?filter[status]=paid')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'paid');
    });

    it('searches invoices by number', function () {
        Invoice::factory()->create(['number' => 'INV-20260829-ABC123']);
        Invoice::factory()->create(['number' => 'INV-20260829-XYZ789']);

        $this->getJson('/api/landlord/invoices?search=ABC123')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.number', 'INV-20260829-ABC123');
    });

    it('searches invoices by tenant name', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        $subscription = Subscription::factory()->for($tenant)->create();
        Invoice::factory()->for($subscription)->create();
        Invoice::factory()->create();

        $this->getJson('/api/landlord/invoices?search=Acme')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.tenant_id', $tenant->id);
    });

    it('returns all invoices when search is blank', function () {
        Invoice::factory()->count(2)->create();

        $this->getJson('/api/landlord/invoices?search=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });

    it('ignores unknown filters instead of querying arbitrary columns', function () {
        Invoice::factory()->create();

        $this->getJson('/api/landlord/invoices?filter[password]=secret')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });

    it('includes the tenant and subscription when requested', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        $subscription = Subscription::factory()->for($tenant)->create();
        Invoice::factory()->for($subscription)->create();

        $this->getJson('/api/landlord/invoices?include=tenant,subscription')
            ->assertOk()
            ->assertJsonPath('data.0.tenant.name', 'Acme Stores')
            ->assertJsonPath('data.0.subscription.id', $subscription->id);
    });
});

describe('store', function () {
    it('issues an open invoice from the subscription terms', function () {
        $this->travelTo('2026-08-29 20:00:00');

        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        $subscription = Subscription::factory()->for($tenant)->create([
            'price' => 7900,
            'currency' => 'NGN',
        ]);

        $this->postJson('/api/landlord/invoices', [
            'subscription_id' => $subscription->id,
            'due_at' => '2026-09-12T20:00:00Z',
            'notes' => 'August usage',
        ])
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.subscription_id', $subscription->id)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.amount', 7900)
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.issued_at', '2026-08-29T20:00:00.000000Z')
            ->assertJsonPath('data.due_at', '2026-09-12T20:00:00.000000Z')
            ->assertJsonPath('data.notes', 'August usage')
            ->assertJsonPath('data.tenant.name', 'Acme Stores');

        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'amount' => 7900,
            'currency' => 'NGN',
            'status' => InvoiceStatus::Open->value,
        ]);
    });

    it('uses billing settings for invoice number prefix, suffix, grace days, and footer notes', function () {
        $this->travelTo('2026-08-29 20:00:00');

        app(SettingService::class)->updateDomain('billing', [
            'billing.invoice_prefix' => 'MRC',
            'billing.invoice_suffix' => 'NG',
            'billing.grace_days' => 7,
            'billing.invoice_memo' => 'Thank you for your business.',
            'billing.invoice_footer' => 'Payable within 7 days.',
        ]);

        $subscription = Subscription::factory()->create([
            'price' => 5000,
            'currency' => 'USD',
        ]);

        $invoice = $this->postJson('/api/landlord/invoices', [
            'subscription_id' => $subscription->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.due_at', '2026-09-05T20:00:00.000000Z')
            ->assertJsonPath('data.notes', "Thank you for your business.\n\nPayable within 7 days.")
            ->json('data');

        expect($invoice['number'])->toStartWith('MRC-20260829-')
            ->and($invoice['number'])->toEndWith('-NG');
    });

    it('does not persist client-supplied amount or status', function () {
        $subscription = Subscription::factory()->create([
            'price' => 2900,
            'currency' => 'USD',
        ]);

        $this->postJson('/api/landlord/invoices', [
            'subscription_id' => $subscription->id,
            'amount' => 1,
            'status' => 'paid',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 2900)
            ->assertJsonPath('data.status', 'open');
    });

    it('returns 422 when the subscription is missing', function () {
        $this->postJson('/api/landlord/invoices', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subscription_id']);
    });

    it('returns 422 when the subscription does not exist', function () {
        $this->postJson('/api/landlord/invoices', [
            'subscription_id' => 999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subscription_id']);
    });
});

describe('show', function () {
    it('returns a single invoice', function () {
        $invoice = Invoice::factory()->create();

        $this->getJson("/api/landlord/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonMissingPath('data.tenant')
            ->assertJsonMissingPath('data.subscription');
    });

    it('returns 404 when the invoice does not exist', function () {
        $this->getJson('/api/landlord/invoices/999')
            ->assertNotFound();
    });
});

describe('update', function () {
    it('updates notes and due date on an open invoice', function () {
        $this->travelTo('2026-08-29 20:00:00');

        $invoice = Invoice::factory()->create();

        $this->putJson("/api/landlord/invoices/{$invoice->id}", [
            'notes' => 'Updated note',
            'due_at' => '2026-09-15T20:00:00Z',
        ])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Updated note')
            ->assertJsonPath('data.due_at', '2026-09-15T20:00:00.000000Z');
    });

    it('returns 422 when updating a paid invoice', function () {
        $invoice = Invoice::factory()->paid()->create();

        $this->putJson("/api/landlord/invoices/{$invoice->id}", [
            'notes' => 'Too late',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('pay', function () {
    beforeEach(function (): void {
        configureFlutterwaveForTests();
    });

    it('initializes payment for an open invoice', function () {
        fakeFlutterwaveInitialize();

        $invoice = Invoice::factory()->create();

        $this->postJson("/api/landlord/invoices/{$invoice->id}/pay")
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.checkout_url', 'https://checkout.test/pay');

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'status' => 'pending',
        ]);
    });

    it('returns 422 when the invoice is already paid', function () {
        $invoice = Invoice::factory()->paid()->create();

        $this->postJson("/api/landlord/invoices/{$invoice->id}/pay")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('void', function () {
    it('voids an open invoice', function () {
        $this->travelTo('2026-08-29 20:00:00');

        $invoice = Invoice::factory()->create();

        $this->postJson("/api/landlord/invoices/{$invoice->id}/void")
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.voided_at', '2026-08-29T20:00:00.000000Z');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Void->value,
        ]);
    });

    it('returns 422 when voiding a paid invoice', function () {
        $invoice = Invoice::factory()->paid()->create();

        $this->postJson("/api/landlord/invoices/{$invoice->id}/void")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('destroy', function () {
    it('does not expose a delete endpoint', function () {
        $invoice = Invoice::factory()->create();

        $this->deleteJson("/api/landlord/invoices/{$invoice->id}")
            ->assertMethodNotAllowed();

        $this->assertNotSoftDeleted($invoice);
    });
});

describe('restore', function () {
    it('does not expose a restore endpoint', function () {
        $invoice = Invoice::factory()->create();
        $invoice->delete();

        $this->postJson("/api/landlord/invoices/{$invoice->id}/restore")
            ->assertNotFound();
    });
});

describe('destroyMany', function () {
    it('does not expose a bulk delete endpoint', function () {
        $first = Invoice::factory()->create();
        $second = Invoice::factory()->create();

        $this->deleteJson('/api/landlord/invoices/destroy-many', [
            'ids' => [$first->id, $second->id],
        ])->assertMethodNotAllowed();

        $this->assertNotSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
    });
});

describe('restoreMany', function () {
    it('does not expose a bulk restore endpoint', function () {
        $invoice = Invoice::factory()->create();
        $invoice->delete();

        $this->postJson('/api/landlord/invoices/restore-many', [
            'ids' => [$invoice->id],
        ])->assertMethodNotAllowed();
    });
});
