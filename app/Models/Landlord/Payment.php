<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\PaymentProvider;
use App\Enums\Landlord\PaymentStatus;
use App\Models\Concerns\LogsLandlordActivity;
use Database\Factories\Landlord\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Landlord payment attempt linked to an invoice and subscription.
 *
 * Stores merchant reference, provider metadata, and settlement timestamps.
 * Amount and currency are snapshotted at creation from the invoice and are
 * the source of truth for verification against provider responses. Terminal
 * statuses must not be reversed by application logic.
 *
 * @property int $amount Amount in minor currency units (integer).
 * @property PaymentStatus $status
 * @property PaymentProvider $provider
 */
#[Fillable([
    'tenant_id',
    'subscription_id',
    'invoice_id',
    'provider',
    'reference',
    'provider_reference',
    'amount',
    'currency',
    'status',
    'payment_method',
    'checkout_url',
    'metadata',
    'provider_response',
    'paid_at',
    'failed_at',
    'refunded_at',
])]
#[Hidden(['provider_response'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, LogsLandlordActivity;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    /**
     * Attribute cast definitions for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'provider' => PaymentProvider::class,
            'metadata' => 'array',
            'provider_response' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * Attributes excluded from Spatie activity logs.
     *
     * @return list<string>
     */
    protected function activitylogExcept(): array
    {
        return ['provider_response'];
    }

    /**
     * Tenant that owns this payment.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Subscription billed by this payment (denormalized from invoice).
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Open invoice this payment settles when successful.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Apply list filters for tenant, invoice, status, and provider.
     *
     * @param  array<string, mixed>|mixed  $filters
     */
    #[Scope]
    protected function filter(Builder $query, mixed $filters): void
    {
        if (! is_array($filters)) {
            return;
        }

        $query
            ->when(filled($filters['tenant_id'] ?? null), fn (Builder $query): Builder => $query->where('tenant_id', $filters['tenant_id']))
            ->when(filled($filters['invoice_id'] ?? null), fn (Builder $query): Builder => $query->where('invoice_id', $filters['invoice_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(filled($filters['provider'] ?? null), fn (Builder $query): Builder => $query->where('provider', $filters['provider']));
    }

    /**
     * Order payments by id, newest first.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('id');
    }
}
