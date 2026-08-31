<?php

use App\Enums\Landlord\InvoiceStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
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

describe('options', function () {
    it('returns invoice options as label and value pairs', function () {
        $invoice = Invoice::factory()->create(['number' => 'INV-20260829-ABC123']);

        $this->getJson('/api/landlord/invoices/options')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'INV-20260829-ABC123')
            ->assertJsonPath('data.0.value', $invoice->id);
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
    it('soft deletes an invoice', function () {
        $invoice = Invoice::factory()->create();

        $this->deleteJson("/api/landlord/invoices/{$invoice->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($invoice);
    });

    it('returns 404 when showing a soft-deleted invoice', function () {
        $invoice = Invoice::factory()->create();
        $invoice->delete();

        $this->getJson("/api/landlord/invoices/{$invoice->id}")
            ->assertNotFound();
    });
});

describe('restore', function () {
    it('restores a soft-deleted invoice', function () {
        $invoice = Invoice::factory()->create();
        $invoice->delete();

        $this->postJson("/api/landlord/invoices/{$invoice->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id);

        $this->assertNotSoftDeleted($invoice);
    });

    it('returns 404 when the invoice is not soft deleted', function () {
        $invoice = Invoice::factory()->create();

        $this->postJson("/api/landlord/invoices/{$invoice->id}/restore")
            ->assertNotFound();
    });
});

describe('destroyMany', function () {
    it('soft deletes the given invoices', function () {
        $first = Invoice::factory()->create();
        $second = Invoice::factory()->create();

        $this->deleteJson('/api/landlord/invoices/destroy-many', [
            'ids' => [$first->id, $second->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
    });

    it('returns 422 when ids are missing', function () {
        $this->deleteJson('/api/landlord/invoices/destroy-many', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted invoices', function () {
        $first = Invoice::factory()->create();
        $second = Invoice::factory()->create();
        $first->delete();
        $second->delete();

        $this->postJson('/api/landlord/invoices/restore-many', [
            'ids' => [$first->id, $second->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
    });

    it('returns 422 when an id is not soft deleted', function () {
        $invoice = Invoice::factory()->create();

        $this->postJson('/api/landlord/invoices/restore-many', [
            'ids' => [$invoice->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });
});
