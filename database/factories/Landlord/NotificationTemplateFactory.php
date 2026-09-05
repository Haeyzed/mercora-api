<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\NoticeChannel;
use App\Models\Landlord\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->slug(2);

        return [
            'key' => $key,
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'channels' => [NoticeChannel::InApp->value, NoticeChannel::Mail->value],
            'variables' => ['name'],
            'title' => 'Hello {{name}}',
            'body' => 'Body for {{name}}',
            'email_subject' => 'Email: {{name}}',
            'email_body' => 'Email body for {{name}}',
            'is_mandatory' => false,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function mandatory(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_mandatory' => true,
        ]);
    }
}
