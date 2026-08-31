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

#[Fillable(['tenant_id', 'subscription_id', 'number', 'status', 'amount', 'currency', 'issued_at', 'period_starts_at', 'period_ends_at', 'due_at', 'paid_at', 'voided_at', 'notes'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use AllowsIncludes, HasFactory, LogsLandlordActivity, SoftDeletes;

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['tenant', 'subscription'];
    }

    /**
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

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('issued_at')->orderByDesc('id');
    }
}
