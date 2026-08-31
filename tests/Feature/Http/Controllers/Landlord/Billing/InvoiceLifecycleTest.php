<?php

use App\Models\Landlord\Invoice;
use App\Models\Landlord\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('issue', function () {
    it('returns the existing invoice when the same period is issued twice', function () {
        $subscription = Subscription::factory()->create();

        $first = $this->postJson('/api/landlord/invoices', [
            'subscription_id' => $subscription->id,
        ])->assertCreated()->json('data.id');

        $second = $this->postJson('/api/landlord/invoices', [
            'subscription_id' => $subscription->id,
        ])->assertCreated()->json('data.id');

        expect($second)->toBe($first);
        $this->assertDatabaseCount('invoices', 1);
    });

    it('returns 422 when the subscription is not current', function () {
        $subscription = Subscription::factory()->canceled()->create();

        $this->postJson('/api/landlord/invoices', [
            'subscription_id' => $subscription->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subscription_id']);
    });
});

describe('transitions', function () {
    it('returns 422 when a void invoice is paid', function () {
        $invoice = Invoice::factory()->void()->create();

        $this->postJson("/api/landlord/invoices/{$invoice->id}/pay")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });

    it('returns 422 when a paid invoice is voided', function () {
        $invoice = Invoice::factory()->paid()->create();

        $this->postJson("/api/landlord/invoices/{$invoice->id}/void")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});
