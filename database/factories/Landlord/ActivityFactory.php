<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Models\Landlord\Activity;
use App\Models\Landlord\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log_name' => 'default',
            'description' => 'Tenant was created',
            'event' => 'created',
            'subject_type' => null,
            'subject_id' => null,
            'causer_type' => null,
            'causer_id' => null,
            'attribute_changes' => null,
            'properties' => null,
        ];
    }

    public function causedBy(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'causer_type' => $user->getMorphClass(),
            'causer_id' => $user->getKey(),
        ]);
    }

    public function forSubject(Model $subject): static
    {
        return $this->state(fn (array $attributes): array => [
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }
}
