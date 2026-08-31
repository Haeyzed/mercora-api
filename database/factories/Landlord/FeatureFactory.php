<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\FeatureType;
use App\Models\Landlord\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->slug(2);

        return [
            'key' => $key,
            'name' => ucwords(str_replace('.', ' ', $key)),
            'description' => fake()->sentence(),
            'type' => FeatureType::Integer,
            'is_active' => true,
        ];
    }
}
