<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\NoticeChannel;
use App\Enums\Landlord\NoticeStatus;
use App\Models\Landlord\Notice;
use App\Models\Landlord\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notice>
 */
class NoticeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Invoice past due',
            'body' => 'Acme Stores has an open invoice that is past due.',
            'channel' => NoticeChannel::InApp,
            'status' => NoticeStatus::Unread,
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => NoticeStatus::Read,
            'read_at' => now(),
        ]);
    }

    public function mail(): static
    {
        return $this->state(fn (array $attributes): array => [
            'channel' => NoticeChannel::Mail,
        ]);
    }
}
