<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use App\Http\Resources\Landlord\Tenants\DomainResource;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Tenants\PublicTenantResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe public tenant bootstrap payload for unauthenticated clients.
 *
 * @property Tenant $resource
 */
class PublicTenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'allows_login' => app(PublicTenantResolver::class)->allowsLogin($tenant),
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
        ];
    }
}
