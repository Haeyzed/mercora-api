<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\SettingType;
use App\Models\Concerns\LogsLandlordActivity;
use Database\Factories\Landlord\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['group', 'key', 'type', 'value', 'description'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, LogsLandlordActivity, SoftDeletes;

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
            ->when(filled($filters['group'] ?? null), fn (Builder $query): Builder => $query->where('group', $filters['group']))
            ->when(filled($filters['key'] ?? null), fn (Builder $query): Builder => $query->where('key', $filters['key']))
            ->when(filled($filters['type'] ?? null), fn (Builder $query): Builder => $query->where('type', $filters['type']));
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
            $query->where('key', 'like', $like)
                ->orWhere('group', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('group')->orderBy('key')->orderBy('id');
    }
}
