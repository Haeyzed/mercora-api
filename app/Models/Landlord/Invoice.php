<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\InvoiceStatus;
use App\Models\Concerns\AllowsIncludes;
use App\Models\Concerns\LogsLandlordActivity;
use Database\Factories\Landlord\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'subscription_id', 'payment_id', 'number', 'status', 'amount', 'subtotal', 'tax_rate', 'tax_amount', 'tax_inclusive', 'currency', 'issued_at', 'period_starts_at', 'period_ends_at', 'due_at', 'paid_at', 'voided_at', 'notes', 'seller'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use AllowsIncludes, HasFactory, LogsLandlordActivity, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
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
            'subtotal' => 'integer',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'integer',
            'tax_inclusive' => 'boolean',
            'seller' => 'array',
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Tenant billed by this invoice.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Subscription this invoice covers.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Payment that settled this invoice, when paid.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Relationship names allowed via Includes query parameters.
     *
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['tenant', 'subscription', 'payment'];
    }

    /**
     * Apply list filters for tenant, subscription, status, and number.
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
            ->when(filled($filters['subscription_id'] ?? null), fn (Builder $query): Builder => $query->where('subscription_id', $filters['subscription_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(filled($filters['number'] ?? null), fn (Builder $query): Builder => $query->where('number', $filters['number']));
    }

    /**
     * Search invoices by number or tenant identity.
     */
    #[Scope]
    protected function search(Builder $query, mixed $term): void
    {
        $term = is_string($term) ? trim($term) : '';

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('number', 'like', $like)
                ->orWhereHas('tenant', function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                });
        });
    }

    /**
     * Order invoices by issue date, newest first.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('issued_at')->orderByDesc('id');
    }
}
