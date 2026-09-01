<?php

use App\Enums\Landlord\FeatureType;
use App\Models\Landlord\Feature;
use App\Models\Landlord\Plan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('store', function () {
    it('creates a feature', function () {
        $this->postJson('/api/landlord/features', [
            'key' => 'max_products',
            'name' => 'Max products',
            'type' => FeatureType::Integer->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'max_products')
            ->assertJsonPath('data.type', 'integer');
    });
});

describe('sync plan features', function () {
    it('replaces entitlement features on a plan', function () {
        $plan = Plan::factory()->create();
        $feature = Feature::factory()->create([
            'key' => 'max_products',
            'type' => FeatureType::Integer,
        ]);

        $this->postJson("/api/landlord/plans/{$plan->id}/features/sync", [
            'features' => [
                ['feature_id' => $feature->id, 'value' => 100],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.features.0.key', 'max_products')
            ->assertJsonPath('data.features.0.value', '100');
    });
});
