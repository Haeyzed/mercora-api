<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\SettingType;
use App\Models\Concerns\LogsLandlordActivity;
use Database\Factories\Landlord\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['group', 'key', 'type', 'value', 'description'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, LogsLandlordActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'string',
    ];

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }

    /**
     * @return list<string>
     */
    protected function activitylogExcept(): array
    {
        return ['value'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
        ];
    }

    public static function encode(SettingType $type, mixed $value): string
    {
        return match ($type) {
            SettingType::Boolean => $value ? '1' : '0',
            SettingType::Integer => (string) $value,
            SettingType::Json => json_encode($value, JSON_THROW_ON_ERROR),
            SettingType::String => (string) $value,
        };
    }

    public function decoded(): mixed
    {
        $stored = $this->getAttributes()['value'] ?? null;

        if ($stored === null) {
            return null;
        }

        return match ($this->type) {
            SettingType::Boolean => $stored === '1',
            SettingType::Integer => (int) $stored,
            SettingType::Json => json_decode($stored, true),
            SettingType::String => $stored,
        };
    }
}
