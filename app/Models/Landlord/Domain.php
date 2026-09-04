<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Models\Concerns\LogsLandlordActivity;
use Database\Factories\Landlord\DomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

#[Fillable(['domain', 'tenant_id'])]
class Domain extends BaseDomain
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory, LogsLandlordActivity;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): DomainFactory
    {
        return DomainFactory::new();
    }

    /**
     * Search domains by hostname.
     */
    #[Scope]
    protected function search(Builder $query, mixed $term): void
    {
        $term = is_string($term) ? trim($term) : '';

        if ($term === '') {
            return;
        }

        $query->where('domain', 'like', '%'.$term.'%');
    }

    /**
     * Order domains by hostname then id.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('domain')->orderBy('id');
    }
}
